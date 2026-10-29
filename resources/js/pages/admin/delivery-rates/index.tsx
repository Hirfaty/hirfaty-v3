import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    Globe,
    MapPin,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import ExportMenu from '@/components/admin/export-menu';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin/layout';
import type { PageProps } from '@/types';

type DeliveryService = {
    id: number;
    slug: string;
    name: string;
    /** `destination`: a fee per city or country. `weight`: zone × weight grid. */
    pricing: 'destination' | 'weight';
};

type Rate = {
    id: number;
    service_name: string;
    country_code: string;
    country_name: string;
    /** Null on an international rate, which is priced per country. */
    city_name: string | null;
    region: string | null;
    /** Set on a weight-priced card instead of a fee. */
    zone: number | null;
    fee: number | null;
};

type AvailableCity = { id: number; name: string };

/** The fees around a weight-priced card's grid. */
type PricingSettings = {
    shipment_fee: number;
    fuel_surcharge_percent: number;
    default_piece_weight: number | null;
    handling_fee: number;
    overweight_fee: number;
};

type GridCell = { id: number; zone: number; price: number };

type GridRow = {
    up_to: number;
    /** Null on a flat band; the step size on a per-step stretch. */
    step: number | null;
    /** Where a per-step stretch starts. */
    from?: number;
    cells: GridCell[];
};

type WeightGrid = {
    zones: number[];
    brackets: GridRow[];
    steps: GridRow[];
};

const money = (value: number) =>
    `${new Intl.NumberFormat('fr-MA', { maximumFractionDigits: 2 }).format(value)} MAD`;

const kg = (value: number) =>
    `${new Intl.NumberFormat('fr-MA', { maximumFractionDigits: 3 }).format(value)} kg`;

const NEW_CITY = 'new';

const inputClass =
    'w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none';

/**
 * An amount that turns into an input on click; Enter saves, Escape cancels.
 * `field` is what the endpoint expects the amount under.
 */
const AmountCell = ({
    value,
    field,
    url,
    compact = false,
}: {
    value: number;
    field: 'fee' | 'price';
    url: string;
    /** For the weight grid: no currency, tighter box. */
    compact?: boolean;
}) => {
    const [editing, setEditing] = useState(false);
    const form = useForm({ amount: String(value) });
    const error = (form.errors as Record<string, string | undefined>)[field];

    const start = () => {
        form.setData('amount', String(value));
        form.clearErrors();
        setEditing(true);
    };

    const save = () => {
        form.transform((data) => ({ [field]: data.amount }));
        form.patch(url, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    if (!editing) {
        return (
            <button
                type="button"
                onClick={start}
                title="Modifier le tarif"
                className={`group inline-flex items-center gap-2 rounded-md font-medium text-ink tabular-nums transition hover:bg-muted ${
                    compact ? 'px-1.5 py-0.5' : 'px-2 py-1'
                }`}
            >
                {compact
                    ? new Intl.NumberFormat('fr-MA', {
                          minimumFractionDigits: 2,
                          maximumFractionDigits: 2,
                      }).format(value)
                    : money(value)}
                {!compact && (
                    <Pencil className="h-3.5 w-3.5 text-ink-soft/50 transition group-hover:text-ink" />
                )}
            </button>
        );
    }

    return (
        <div className="inline-flex flex-col items-end gap-1">
            <div className="inline-flex items-center gap-1.5">
                <input
                    autoFocus
                    type="number"
                    min={0}
                    step="0.01"
                    value={form.data.amount}
                    onChange={(e) => form.setData('amount', e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            save();
                        }

                        if (e.key === 'Escape') {
                            setEditing(false);
                        }
                    }}
                    className="w-24 rounded-md border border-border bg-card px-2 py-1 text-right text-sm tabular-nums focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                />
                {!compact && <span className="text-xs text-ink-soft">MAD</span>}
                <button
                    type="button"
                    onClick={save}
                    disabled={form.processing}
                    aria-label="Enregistrer"
                    className="rounded-md p-1.5 text-emerald-700 transition hover:bg-emerald-50 disabled:opacity-50"
                >
                    <Check className="h-4 w-4" />
                </button>
                <button
                    type="button"
                    onClick={() => setEditing(false)}
                    aria-label="Annuler"
                    className="rounded-md p-1.5 text-ink-soft transition hover:bg-muted"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>
            {error && <p className="text-xs text-red-600">{error}</p>}
        </div>
    );
};

/** A weight-priced country's zone, moved with a select. */
const ZoneCell = ({ rate, zones }: { rate: Rate; zones: number[] }) => (
    <select
        value={rate.zone ?? ''}
        aria-label={`Zone de ${rate.country_name}`}
        onChange={(e) =>
            router.patch(
                route('admin.delivery-rates.update', rate.id),
                { zone: Number(e.target.value) },
                { preserveScroll: true },
            )
        }
        className="rounded-md border border-border bg-card px-2 py-1 text-sm text-ink tabular-nums focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
    >
        {rate.zone === null && <option value="">—</option>}
        {zones.map((zone) => (
            <option key={zone} value={zone}>
                Zone {zone}
            </option>
        ))}
    </select>
);

/**
 * One labelled number field of the pricing panel. The hint sits outside the
 * <label>: app.css sets every label in caps, which suits a caption and not a
 * sentence.
 */
const SettingField = ({
    id,
    label,
    hint,
    suffix,
    value,
    error,
    step = '0.01',
    onChange,
}: {
    id: string;
    label: string;
    hint: string;
    suffix: string;
    value: string;
    error?: string;
    step?: string;
    onChange: (value: string) => void;
}) => (
    <div className="grid content-start gap-1.5">
        <label htmlFor={id} className="text-sm font-medium text-ink">
            {label}
        </label>
        <div className="relative">
            <input
                id={id}
                type="number"
                min={0}
                step={step}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className={`${inputClass} pr-16 tabular-nums`}
            />
            <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-ink-soft">
                {suffix}
            </span>
        </div>
        <p className="text-xs text-ink-soft">{hint}</p>
        {error && <p className="text-xs text-red-600">{error}</p>}
    </div>
);

/**
 * The fees a weight-priced quote adds around the grid price. The fuel
 * surcharge moves every week, so it sits first and is flagged while it is 0.
 */
const PricingPanel = ({
    service,
    pricing,
}: {
    service: DeliveryService;
    pricing: PricingSettings;
}) => {
    const form = useForm({
        fuel_surcharge_percent: String(pricing.fuel_surcharge_percent),
        shipment_fee: String(pricing.shipment_fee),
        default_piece_weight: String(pricing.default_piece_weight ?? ''),
        handling_fee: String(pricing.handling_fee),
        overweight_fee: String(pricing.overweight_fee),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch(
            route('admin.delivery-services.pricing.update', service.id),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <form
            onSubmit={submit}
            className="rounded-xl border border-border bg-card p-5 shadow-sm"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-ink">
                        Tarification au poids
                    </h2>
                    <p className="mt-1 max-w-2xl text-sm text-ink-soft">
                        Prix facturé à l’acheteur : grille de la zone pour le
                        poids du colis, + manutention par pièce encombrante ou
                        lourde, + surcharge carburant sur ces deux montants, +
                        frais fixes par envoi.
                    </p>
                </div>
                {pricing.fuel_surcharge_percent === 0 && (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">
                        <AlertTriangle className="h-3.5 w-3.5" />
                        Surcharge carburant à 0 % — à renseigner
                    </span>
                )}
            </div>

            <div className="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <SettingField
                    id="pricing-fuel"
                    label="Surcharge carburant"
                    hint="Change chaque semaine : reprendre le taux affiché sur le site DHL."
                    suffix="%"
                    value={form.data.fuel_surcharge_percent}
                    error={form.errors.fuel_surcharge_percent}
                    onChange={(v) => form.setData('fuel_surcharge_percent', v)}
                />
                <SettingField
                    id="pricing-shipment-fee"
                    label="Frais fixes par envoi"
                    hint="Frais de service local de 150 MAD + TVA 20 %."
                    suffix="MAD TTC"
                    value={form.data.shipment_fee}
                    error={form.errors.shipment_fee}
                    onChange={(v) => form.setData('shipment_fee', v)}
                />
                <SettingField
                    id="pricing-default-weight"
                    label="Poids par défaut d’une pièce"
                    hint="Utilisé quand le poids d’un produit n’est pas renseigné."
                    suffix="kg"
                    step="0.001"
                    value={form.data.default_piece_weight}
                    error={form.errors.default_piece_weight}
                    onChange={(v) => form.setData('default_piece_weight', v)}
                />
                <SettingField
                    id="pricing-handling"
                    label="Manutention supplémentaire"
                    hint="Par pièce de plus de 100 cm, de plus de 80 cm sur son 2ᵉ côté ou de plus de 25 kg."
                    suffix="MAD"
                    value={form.data.handling_fee}
                    error={form.errors.handling_fee}
                    onChange={(v) => form.setData('handling_fee', v)}
                />
                <SettingField
                    id="pricing-overweight"
                    label="Pièce hors normes"
                    hint="Par pièce de plus de 70 kg (réel ou volumétrique), à la place de la manutention."
                    suffix="MAD"
                    value={form.data.overweight_fee}
                    error={form.errors.overweight_fee}
                    onChange={(v) => form.setData('overweight_fee', v)}
                />
            </div>

            <div className="mt-5 flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    Enregistrer
                </Button>
            </div>
        </form>
    );
};

/**
 * A weight-priced card's grid as the carrier prints it: a row per weight, a
 * column per zone — flat prices to the last bracket, then a price per extra
 * step. Every cell is editable in place.
 */
const WeightGridTable = ({ grid }: { grid: WeightGrid }) => {
    const row = (key: string, label: ReactNode, cells: GridCell[]) => (
        <tr
            key={key}
            className="border-b border-border/60 last:border-0 hover:bg-muted/40"
        >
            <th
                scope="row"
                className="px-4 py-1.5 text-left font-normal whitespace-nowrap text-ink-soft"
            >
                {label}
            </th>
            {grid.zones.map((zone) => {
                const cell = cells.find((c) => c.zone === zone);

                return (
                    <td key={zone} className="px-2 py-1 text-right">
                        {cell ? (
                            <AmountCell
                                value={cell.price}
                                field="price"
                                url={route(
                                    'admin.delivery-weight-bands.update',
                                    cell.id,
                                )}
                                compact
                            />
                        ) : (
                            <span className="text-ink-soft/50">—</span>
                        )}
                    </td>
                );
            })}
        </tr>
    );

    return (
        <div className="rounded-xl border border-border bg-card shadow-sm">
            <div className="border-b border-border px-5 py-4">
                <h2 className="text-base font-semibold text-ink">
                    Grille de prix par zone
                </h2>
                <p className="mt-1 text-sm text-ink-soft">
                    En MAD, hors frais fixes et surcharges. Le poids facturé est
                    la somme, pièce par pièce, du plus élevé du poids réel et du
                    poids volumétrique (L × l × h ÷ 5 000).
                </p>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-border">
                            <th className="px-4 py-2.5 text-left font-medium text-ink-soft">
                                Poids
                            </th>
                            {grid.zones.map((zone) => (
                                <th
                                    key={zone}
                                    className="px-2 py-2.5 text-right font-medium text-ink-soft"
                                >
                                    Zone {zone}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {grid.brackets.map((bracket) =>
                            row(
                                `b-${bracket.up_to}`,
                                `≤ ${kg(bracket.up_to)}`,
                                bracket.cells,
                            ),
                        )}
                        {grid.steps.length > 0 && (
                            <tr className="border-b border-border bg-muted/40">
                                <td
                                    colSpan={grid.zones.length + 1}
                                    className="px-4 py-2 text-xs font-medium tracking-wide text-ink-soft uppercase"
                                >
                                    Au-delà, par tranche supplémentaire
                                </td>
                            </tr>
                        )}
                        {grid.steps.map((step) =>
                            row(
                                `s-${step.up_to}`,
                                step.up_to >= 99999
                                    ? `> ${kg(step.from ?? 0)} · par ${kg(step.step ?? 1)}`
                                    : `${kg(step.from ?? 0)} – ${kg(step.up_to)} · par ${kg(step.step ?? 1)}`,
                                step.cells,
                            ),
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

/** Add a Moroccan city or a foreign country to the selected card. */
const AddDestinationDialog = ({
    kind,
    service,
    zones,
    availableCities,
    onClose,
}: {
    kind: 'city' | 'country' | null;
    service: DeliveryService;
    /** The zones a country can go in, on a weight-priced card. */
    zones: number[];
    availableCities: AvailableCity[];
    onClose: () => void;
}) => {
    const weighed = service.pricing === 'weight';

    const form = useForm({
        delivery_service_id: service.id,
        type: 'city' as 'city' | 'country',
        fee: '',
        zone: '',
        city_id: '',
        city_name: '',
        city_name_ar: '',
        city_name_en: '',
        region: '',
        country_code: '',
        country_name: '',
    });

    // With no unrated city left, the only way to add one is to create it.
    const creatingCity =
        form.data.city_id === NEW_CITY || availableCities.length === 0;

    const submit = (e: FormEvent) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            type: kind ?? 'city',
            delivery_service_id: service.id,
            city_id: creatingCity || data.city_id === '' ? '' : data.city_id,
            country_code: data.country_code.trim().toUpperCase(),
        }));

        form.post(route('admin.delivery-rates.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    const close = () => {
        form.reset();
        form.clearErrors();
        onClose();
    };

    return (
        <Dialog open={kind !== null} onOpenChange={(open) => !open && close()}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {kind === 'country'
                                ? 'Ajouter un pays'
                                : 'Ajouter une ville'}
                        </DialogTitle>
                        <DialogDescription>
                            {kind === 'country'
                                ? weighed
                                    ? `Les acheteurs pourront se faire livrer dans ce pays via ${service.name}, au prix de sa zone selon le poids du colis.`
                                    : `Les acheteurs pourront se faire livrer dans ce pays via ${service.name}, à un tarif unique.`
                                : `Les acheteurs pourront se faire livrer dans cette ville marocaine via ${service.name}.`}
                        </DialogDescription>
                    </DialogHeader>

                    {kind === 'city' && (
                        <>
                            {availableCities.length > 0 && (
                                <label className="grid gap-1.5 text-sm font-medium text-ink">
                                    Ville
                                    <select
                                        value={form.data.city_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'city_id',
                                                e.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    >
                                        <option value="">
                                            Choisir une ville…
                                        </option>
                                        {availableCities.map((city) => (
                                            <option
                                                key={city.id}
                                                value={city.id}
                                            >
                                                {city.name}
                                            </option>
                                        ))}
                                        <option value={NEW_CITY}>
                                            + Nouvelle ville…
                                        </option>
                                    </select>
                                    {form.errors.city_id && (
                                        <span className="text-xs font-normal text-red-600">
                                            {form.errors.city_id}
                                        </span>
                                    )}
                                </label>
                            )}

                            {creatingCity && (
                                <>
                                    <label className="grid gap-1.5 text-sm font-medium text-ink">
                                        Nom de la ville (en français)
                                        <input
                                            value={form.data.city_name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'city_name',
                                                    e.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        />
                                        {(form.errors.city_name ||
                                            (availableCities.length === 0 &&
                                                form.errors.city_id)) && (
                                            <span className="text-xs font-normal text-red-600">
                                                {form.errors.city_name ??
                                                    form.errors.city_id}
                                            </span>
                                        )}
                                    </label>
                                    {/*
                                        Left blank, the server fills in the
                                        names it knows for common Moroccan
                                        cities; otherwise the French name
                                        shows in every language.
                                    */}
                                    <div className="grid grid-cols-2 gap-3">
                                        <label className="grid gap-1.5 text-sm font-medium text-ink">
                                            Nom en arabe
                                            <input
                                                value={form.data.city_name_ar}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'city_name_ar',
                                                        e.target.value,
                                                    )
                                                }
                                                dir="rtl"
                                                lang="ar"
                                                placeholder="facultatif"
                                                className={inputClass}
                                            />
                                        </label>
                                        <label className="grid gap-1.5 text-sm font-medium text-ink">
                                            Nom en anglais
                                            <input
                                                value={form.data.city_name_en}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'city_name_en',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="facultatif"
                                                className={inputClass}
                                            />
                                        </label>
                                    </div>
                                    <label className="grid gap-1.5 text-sm font-medium text-ink">
                                        Région (facultatif)
                                        <input
                                            value={form.data.region}
                                            onChange={(e) =>
                                                form.setData(
                                                    'region',
                                                    e.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        />
                                    </label>
                                </>
                            )}
                        </>
                    )}

                    {kind === 'country' && (
                        <>
                            <label className="grid gap-1.5 text-sm font-medium text-ink">
                                Nom du pays
                                <input
                                    value={form.data.country_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'country_name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Portugal"
                                    className={inputClass}
                                />
                                {form.errors.country_name && (
                                    <span className="text-xs font-normal text-red-600">
                                        {form.errors.country_name}
                                    </span>
                                )}
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium text-ink">
                                Code pays (ISO, 2 lettres)
                                <input
                                    value={form.data.country_code}
                                    onChange={(e) =>
                                        form.setData(
                                            'country_code',
                                            e.target.value,
                                        )
                                    }
                                    maxLength={2}
                                    placeholder="PT"
                                    className={`${inputClass} uppercase`}
                                />
                                {form.errors.country_code && (
                                    <span className="text-xs font-normal text-red-600">
                                        {form.errors.country_code}
                                    </span>
                                )}
                            </label>
                        </>
                    )}

                    {weighed ? (
                        <label className="grid gap-1.5 text-sm font-medium text-ink">
                            Zone
                            <select
                                value={form.data.zone}
                                onChange={(e) =>
                                    form.setData('zone', e.target.value)
                                }
                                className={inputClass}
                            >
                                <option value="">Choisir une zone…</option>
                                {zones.map((zone) => (
                                    <option key={zone} value={zone}>
                                        Zone {zone}
                                    </option>
                                ))}
                            </select>
                            {(form.errors.zone || form.errors.type) && (
                                <span className="text-xs font-normal text-red-600">
                                    {form.errors.zone ?? form.errors.type}
                                </span>
                            )}
                        </label>
                    ) : (
                        <label className="grid gap-1.5 text-sm font-medium text-ink">
                            Frais de livraison (MAD)
                            <input
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.data.fee}
                                onChange={(e) =>
                                    form.setData('fee', e.target.value)
                                }
                                className={inputClass}
                            />
                            {form.errors.fee && (
                                <span className="text-xs font-normal text-red-600">
                                    {form.errors.fee}
                                </span>
                            )}
                        </label>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={close}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Ajouter
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
};

/**
 * The delivery service rate cards, editable.
 *
 * The desk re-prices any line in place, and adds or removes a Moroccan city or
 * a foreign country. Checkout offers exactly the destinations on the cards,
 * so a row here is a destination buyers can pick. Each carrier has its own
 * card: Cathedis's prices Moroccan cities one by one; DHL's puts each country in
 * a zone and prices the parcel's weight on that zone's grid, edited below the
 * list together with the fees around it.
 */
const Index = ({
    services,
    selectedService,
    rates,
    availableCities,
    pricing,
    grid,
}: PageProps & {
    services: DeliveryService[];
    selectedService: number | null;
    rates: Rate[];
    availableCities: AvailableCity[];
    /** Only on a weight-priced card. */
    pricing: PricingSettings | null;
    grid: WeightGrid | null;
}) => {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');
    const [adding, setAdding] = useState<'city' | 'country' | null>(null);

    const selected =
        services.find((s) => s.id === selectedService) ?? services[0];
    const weighed = selected?.pricing === 'weight';
    const zones = grid?.zones ?? [];

    const filtered = useMemo(() => {
        const needle = search.trim().toLowerCase();

        if (!needle) {
            return rates;
        }

        return rates.filter(
            (rate) =>
                rate.country_name.toLowerCase().includes(needle) ||
                rate.country_code.toLowerCase().includes(needle) ||
                (rate.city_name ?? '').toLowerCase().includes(needle) ||
                (rate.region ?? '').toLowerCase().includes(needle) ||
                (rate.zone !== null && `zone ${rate.zone}` === needle),
        );
    }, [rates, search]);

    const domesticCount = rates.filter((r) => r.city_name !== null).length;
    const internationalCount = rates.length - domesticCount;

    const visit = (service: DeliveryService) =>
        router.get(
            route(
                'admin.delivery-rates.index',
                { service: service.slug },
                false,
            ),
            {},
            { preserveScroll: true, replace: true },
        );

    const remove = (rate: Rate) => {
        const destination = rate.city_name ?? rate.country_name;

        if (
            confirm(
                `Retirer « ${destination} » de la grille ? Les acheteurs ne pourront plus s’y faire livrer.`,
            )
        ) {
            router.delete(route('admin.delivery-rates.destroy', rate.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title={t('titles.admin.delivery_rates')} />

            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        Tarifs de livraison
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        {weighed ? (
                            <>
                                {selected?.name} · {internationalCount} pays ·
                                prix selon la zone et le poids du colis
                            </>
                        ) : (
                            <>
                                {selected?.name} · {domesticCount} ville
                                {domesticCount > 1 ? 's' : ''} au Maroc ·{' '}
                                {internationalCount} pays à l’international
                            </>
                        )}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    {/*
                     * Segmented control to swap carrier cards. Hidden while
                     * there is one service: a lone button is just a label.
                     */}
                    {services.length > 1 && (
                        <div
                            role="tablist"
                            aria-label="Prestataires de livraison"
                            className="inline-flex rounded-lg border border-border bg-card p-1 shadow-sm"
                        >
                            {services.map((service) => {
                                const active = service.id === selected?.id;

                                return (
                                    <button
                                        key={service.id}
                                        type="button"
                                        role="tab"
                                        aria-selected={active}
                                        onClick={() => visit(service)}
                                        className={`rounded-md px-4 py-1.5 text-sm transition ${
                                            active
                                                ? 'bg-ink text-card shadow-sm'
                                                : 'text-ink-soft hover:text-ink'
                                        }`}
                                    >
                                        {service.name}
                                    </button>
                                );
                            })}
                        </div>
                    )}

                    <ExportMenu
                        routeName="admin.delivery-rates.export"
                        filters={{ service: selected?.slug }}
                    />

                    {/* A weight-priced carrier (DHL) ships abroad only. */}
                    {!weighed && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAdding('city')}
                        >
                            <Plus className="h-4 w-4" /> Ville
                        </Button>
                    )}
                    <Button type="button" onClick={() => setAdding('country')}>
                        <Plus className="h-4 w-4" /> Pays
                    </Button>
                </div>
            </header>

            {weighed && selected && pricing && (
                <PricingPanel
                    // Remounted after a save so the fields show what was stored.
                    key={JSON.stringify(pricing)}
                    service={selected}
                    pricing={pricing}
                />
            )}

            <div className="flex flex-wrap items-center gap-3">
                <div className="relative max-w-md flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft/50" />
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={
                            weighed
                                ? 'Pays, code ou « zone 3 »…'
                                : 'Ville, pays ou région…'
                        }
                        className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                    />
                </div>
            </div>

            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-border text-left">
                            <th className="px-5 py-3 font-medium text-ink-soft">
                                Destination
                            </th>
                            <th className="px-5 py-3 font-medium text-ink-soft">
                                Pays
                            </th>
                            {!weighed && (
                                <th className="px-5 py-3 font-medium text-ink-soft">
                                    Région
                                </th>
                            )}
                            <th className="px-5 py-3 text-right font-medium text-ink-soft">
                                {weighed ? 'Zone' : 'Frais'}
                            </th>
                            <th className="w-12 px-3 py-3">
                                <span className="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={weighed ? 4 : 5}
                                    className="h-32 px-5 text-center text-ink-soft"
                                >
                                    Aucun tarif ne correspond à cette recherche.
                                </td>
                            </tr>
                        ) : (
                            filtered.map((rate) => (
                                <tr
                                    key={rate.id}
                                    className="border-b border-border/60 last:border-0 hover:bg-muted/40"
                                >
                                    <td className="px-5 py-3.5">
                                        <div className="flex items-center gap-2.5">
                                            {rate.city_name ? (
                                                <MapPin className="h-4 w-4 shrink-0 text-ink-soft/60" />
                                            ) : (
                                                <Globe className="h-4 w-4 shrink-0 text-ink-soft/60" />
                                            )}
                                            <span className="font-medium text-ink">
                                                {rate.city_name ??
                                                    rate.country_name}
                                            </span>
                                            {!rate.city_name && !weighed && (
                                                <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-ink-soft">
                                                    Tout le pays
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-5 py-3.5 text-ink-soft">
                                        {rate.country_name}{' '}
                                        <span className="text-ink-soft/60">
                                            ({rate.country_code})
                                        </span>
                                    </td>
                                    {!weighed && (
                                        <td className="px-5 py-3.5 text-ink-soft">
                                            {rate.region ?? '—'}
                                        </td>
                                    )}
                                    <td className="px-5 py-3 text-right">
                                        {weighed ? (
                                            <ZoneCell
                                                rate={rate}
                                                zones={zones}
                                            />
                                        ) : (
                                            <AmountCell
                                                key={rate.id}
                                                value={rate.fee ?? 0}
                                                field="fee"
                                                url={route(
                                                    'admin.delivery-rates.update',
                                                    rate.id,
                                                )}
                                            />
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <button
                                            type="button"
                                            onClick={() => remove(rate)}
                                            aria-label={`Retirer ${rate.city_name ?? rate.country_name}`}
                                            className="rounded-md p-2 text-ink-soft transition hover:bg-red-50 hover:text-red-600"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {weighed && grid && <WeightGridTable grid={grid} />}

            {selected && (
                <AddDestinationDialog
                    // Remounted per open so each add starts from a blank form.
                    key={`${selected.id}-${adding ?? 'closed'}`}
                    kind={adding}
                    service={selected}
                    zones={zones}
                    availableCities={availableCities}
                    onClose={() => setAdding(null)}
                />
            )}
        </div>
    );
};

Index.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Index;
