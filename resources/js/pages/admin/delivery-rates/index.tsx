import { Head, router, useForm } from '@inertiajs/react';
import {
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
};

type Rate = {
    id: number;
    service_name: string;
    country_code: string;
    country_name: string;
    /** Null on an international rate, which is priced per country. */
    city_name: string | null;
    region: string | null;
    fee: number;
};

type AvailableCity = { id: number; name: string };

const money = (value: number) =>
    `${new Intl.NumberFormat('fr-MA', { maximumFractionDigits: 2 }).format(value)} MAD`;

const NEW_CITY = 'new';

const inputClass =
    'w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none';

/** A fee that turns into an input on click; Enter saves, Escape cancels. */
const FeeCell = ({ rate }: { rate: Rate }) => {
    const [editing, setEditing] = useState(false);
    const form = useForm({ fee: String(rate.fee) });

    const start = () => {
        form.setData('fee', String(rate.fee));
        form.clearErrors();
        setEditing(true);
    };

    const save = () => {
        form.patch(route('admin.delivery-rates.update', rate.id), {
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
                className="group inline-flex items-center gap-2 rounded-md px-2 py-1 font-medium text-ink tabular-nums transition hover:bg-muted"
            >
                {money(rate.fee)}
                <Pencil className="h-3.5 w-3.5 text-ink-soft/50 transition group-hover:text-ink" />
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
                    value={form.data.fee}
                    onChange={(e) => form.setData('fee', e.target.value)}
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
                <span className="text-xs text-ink-soft">MAD</span>
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
            {form.errors.fee && (
                <p className="text-xs text-red-600">{form.errors.fee}</p>
            )}
        </div>
    );
};

/** Add a Moroccan city or a foreign country to the selected card. */
const AddDestinationDialog = ({
    kind,
    serviceId,
    serviceName,
    availableCities,
    onClose,
}: {
    kind: 'city' | 'country' | null;
    serviceId: number;
    serviceName: string;
    availableCities: AvailableCity[];
    onClose: () => void;
}) => {
    const form = useForm({
        delivery_service_id: serviceId,
        type: 'city' as 'city' | 'country',
        fee: '',
        city_id: '',
        city_name: '',
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
            delivery_service_id: serviceId,
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
                                ? `Les acheteurs pourront se faire livrer dans ce pays via ${serviceName}, à un tarif unique.`
                                : `Les acheteurs pourront se faire livrer dans cette ville marocaine via ${serviceName}.`}
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
                                        Nom de la ville
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
 * a foreign country. Checkout offers exactly the destinations on the selected
 * card, so a row here is a destination buyers can pick. Each carrier has its
 * own card; the selector above switches between them once a second carrier
 * (DHL, say) exists.
 */
const Index = ({
    services,
    selectedService,
    rates,
    availableCities,
}: PageProps & {
    services: DeliveryService[];
    selectedService: number | null;
    rates: Rate[];
    availableCities: AvailableCity[];
}) => {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');
    const [adding, setAdding] = useState<'city' | 'country' | null>(null);

    const selected =
        services.find((s) => s.id === selectedService) ?? services[0];

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
                (rate.region ?? '').toLowerCase().includes(needle),
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
                        {selected?.name} · {domesticCount} ville
                        {domesticCount > 1 ? 's' : ''} au Maroc ·{' '}
                        {internationalCount} pays à l’international
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

                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setAdding('city')}
                    >
                        <Plus className="h-4 w-4" /> Ville
                    </Button>
                    <Button type="button" onClick={() => setAdding('country')}>
                        <Plus className="h-4 w-4" /> Pays
                    </Button>
                </div>
            </header>

            <div className="flex flex-wrap items-center gap-3">
                <div className="relative max-w-md flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft/50" />
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Ville, pays ou région…"
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
                            <th className="px-5 py-3 font-medium text-ink-soft">
                                Région
                            </th>
                            <th className="px-5 py-3 text-right font-medium text-ink-soft">
                                Frais
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
                                    colSpan={5}
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
                                            {!rate.city_name && (
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
                                    <td className="px-5 py-3.5 text-ink-soft">
                                        {rate.region ?? '—'}
                                    </td>
                                    <td className="px-5 py-3 text-right">
                                        <FeeCell key={rate.id} rate={rate} />
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

            {selected && (
                <AddDestinationDialog
                    // Remounted per open so each add starts from a blank form.
                    key={adding ?? 'closed'}
                    kind={adding}
                    serviceId={selected.id}
                    serviceName={selected.name}
                    availableCities={availableCities}
                    onClose={() => setAdding(null)}
                />
            )}
        </div>
    );
};

Index.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Index;
