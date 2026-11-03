import { router } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/** The parcel as Hirfaty would send it — CathedisShipmentService::draft. */
export type CathedisDraft = {
    recipient: string;
    address: string;
    phone: string;
    /** Our locality, to show which Cathedis city it maps to. */
    city_name: string | null;
    cathedis_city_id: number | null;
    sector: string | null;
    amount: number;
    payment_type: string;
    subject: string;
    range_weight: string;
    weight: number | null;
    fragile: boolean;
    declared_value: number;
    allow_opening: boolean;
    package_count: number;
    comment: string;
    remember_city: boolean;
};

type CathedisCityOption = {
    id: number;
    name: string;
    sectors: { id: number; name: string }[];
    default_sector: string;
};

const HEAVY = 'Plus de 30Kg';

const PAYMENT_LABEL: Record<string, string> = {
    ESPECES: 'Espèces à la livraison',
    PAYE: 'Déjà payé',
    CHEQUE: 'Chèque',
    TPE: 'Carte (TPE du livreur)',
    'EFFET DE COMMERCE': 'Effet de commerce',
};

/** Fetched once per page: Cathedis's served cities with their sectors. */
let citiesCache: Promise<CathedisCityOption[]> | null = null;

function loadCities(): Promise<CathedisCityOption[]> {
    citiesCache ??= fetch(route('admin.cathedis.cities'), {
        headers: { Accept: 'application/json' },
    }).then((response) => {
        if (!response.ok) {
            citiesCache = null;

            throw new Error('cities');
        }

        return response.json();
    });

    return citiesCache;
}

const inputClass =
    'w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-ink transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none';

function Field({
    label,
    hint,
    children,
}: {
    label: string;
    hint?: ReactNode;
    children: ReactNode;
}) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-xs font-medium text-ink">
                {label}
            </span>
            {children}
            {hint && (
                <span className="mt-1 block text-xs text-ink-soft">{hint}</span>
            )}
        </label>
    );
}

/**
 * The parcel the desk is about to hand Cathedis, every field filled from the
 * orders and editable — a phone typo, the Cathedis city of a locality it
 * spells differently, the amount to collect. The server checks it all again
 * (Admin\CathedisShipmentController::store, CathedisShipmentService::create).
 */
export default function CathedisParcelDialog({
    open,
    onOpenChange,
    reference,
    draft,
    warnings,
    ranges,
    paymentTypes,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The order the request is made against — any order of the purchase. */
    reference: string;
    draft: CathedisDraft;
    warnings: string[];
    ranges: string[];
    paymentTypes: string[];
}) {
    const [form, setForm] = useState<CathedisDraft>(draft);
    const [cities, setCities] = useState<CathedisCityOption[] | null>(null);
    const [search, setSearch] = useState('');
    const [busy, setBusy] = useState(false);

    const set = <K extends keyof CathedisDraft>(
        key: K,
        value: CathedisDraft[K],
    ) => setForm((current) => ({ ...current, [key]: value }));

    useEffect(() => {
        if (!open || cities) {
            return;
        }

        loadCities()
            .then(setCities)
            .catch(() =>
                toast.error('Liste des villes Cathedis indisponible.'),
            );
    }, [open, cities]);

    const city = cities?.find((c) => c.id === form.cathedis_city_id) ?? null;
    const needle = search.trim().toLowerCase();
    const shown = (cities ?? []).filter(
        (c) =>
            c.id === form.cathedis_city_id ||
            needle === '' ||
            c.name.toLowerCase().includes(needle),
    );

    const pickCity = (id: number) => {
        const picked = cities?.find((c) => c.id === id);

        setForm((current) => ({
            ...current,
            cathedis_city_id: picked ? picked.id : null,
            sector: picked ? picked.default_sector : null,
        }));
    };

    const fragileTooCheap = form.fragile && form.declared_value <= 250;
    const heavyWithoutWeight =
        form.range_weight === HEAVY && (form.weight ?? 0) < 30;
    const incomplete =
        form.cathedis_city_id === null ||
        !form.sector ||
        form.recipient.trim() === '' ||
        form.address.trim() === '' ||
        form.phone.trim() === '';

    const submit = () => {
        setBusy(true);

        router.post(
            route('admin.orders.cathedis.store', reference),
            {
                ...form,
                comment: form.comment.trim() || null,
                weight: form.range_weight === HEAVY ? form.weight : null,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const flash = (
                        page.props as {
                            flash?: { success?: string; error?: string };
                        }
                    ).flash;

                    if (flash?.error) {
                        toast.error(flash.error);

                        return;
                    }

                    toast.success(flash?.success ?? 'Colis Cathedis créé.');
                    onOpenChange(false);
                },
                onError: (errors) =>
                    toast.error(
                        (Object.values(errors)[0] as string) ??
                            'Colis non créé.',
                    ),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Créer le colis Cathedis</DialogTitle>
                    <DialogDescription>
                        Vérifiez ce qui sera envoyé à Cathedis. Le colis est
                        créé « En attente de ramassage » et son code devient le
                        numéro de suivi de tout l’achat.
                    </DialogDescription>
                </DialogHeader>

                {warnings.length > 0 && (
                    <div className="space-y-1.5 rounded-lg bg-amber-50 px-3 py-2.5 text-xs leading-relaxed text-amber-800">
                        {warnings.map((warning) => (
                            <p key={warning} className="flex gap-1.5">
                                <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                {warning}
                            </p>
                        ))}
                    </div>
                )}

                <section className="space-y-3">
                    <h3 className="text-xs font-semibold tracking-wide text-ink-soft uppercase">
                        Destinataire
                    </h3>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="Nom">
                            <input
                                value={form.recipient}
                                onChange={(e) =>
                                    set('recipient', e.target.value)
                                }
                                maxLength={255}
                                className={inputClass}
                            />
                        </Field>
                        <Field label="Téléphone" hint="06…, 07…, 05… ou +212…">
                            <input
                                value={form.phone}
                                onChange={(e) => set('phone', e.target.value)}
                                maxLength={30}
                                inputMode="tel"
                                className={inputClass}
                            />
                        </Field>
                    </div>
                    <Field label="Adresse">
                        <textarea
                            value={form.address}
                            onChange={(e) => set('address', e.target.value)}
                            rows={2}
                            maxLength={255}
                            className={`${inputClass} resize-none`}
                        />
                    </Field>
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Rechercher une ville Cathedis…"
                        aria-label="Rechercher une ville Cathedis"
                        className={inputClass}
                    />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field
                            label="Ville Cathedis"
                            hint={
                                form.city_name &&
                                `Ville de la commande : ${form.city_name}`
                            }
                        >
                            <select
                                value={form.cathedis_city_id ?? ''}
                                onChange={(e) =>
                                    pickCity(Number(e.target.value))
                                }
                                disabled={!cities}
                                className={inputClass}
                            >
                                <option value="" disabled>
                                    {cities ? 'Choisir…' : 'Chargement…'}
                                </option>
                                {shown.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field
                            label="Secteur"
                            hint="« Autre » quand le quartier n’est pas listé."
                        >
                            <select
                                value={form.sector ?? ''}
                                onChange={(e) => set('sector', e.target.value)}
                                disabled={!city}
                                className={inputClass}
                            >
                                {!city && <option value="">—</option>}
                                {city?.sectors.length === 0 && (
                                    <option value={form.sector ?? 'Autre'}>
                                        {form.sector ?? 'Autre'}
                                    </option>
                                )}
                                {city?.sectors.map((s) => (
                                    <option key={s.id} value={s.name}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>
                    {form.city_name && (
                        <label className="flex cursor-pointer items-center gap-2 text-xs text-ink">
                            <input
                                type="checkbox"
                                checked={form.remember_city}
                                onChange={(e) =>
                                    set('remember_city', e.target.checked)
                                }
                                className="h-4 w-4 rounded border-border text-ink focus:ring-ink/20"
                            />
                            Retenir cette ville Cathedis pour « {form.city_name}{' '}
                            » (prochains colis)
                        </label>
                    )}
                </section>

                <section className="space-y-3">
                    <h3 className="text-xs font-semibold tracking-wide text-ink-soft uppercase">
                        Colis
                    </h3>
                    <Field label="Contenu (imprimé sur le bon)">
                        <input
                            value={form.subject}
                            onChange={(e) => set('subject', e.target.value)}
                            maxLength={255}
                            className={inputClass}
                        />
                    </Field>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Field label="Poids">
                            <select
                                value={form.range_weight}
                                onChange={(e) =>
                                    set('range_weight', e.target.value)
                                }
                                className={inputClass}
                            >
                                {ranges.map((range) => (
                                    <option key={range} value={range}>
                                        {range}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        {form.range_weight === HEAVY && (
                            <Field
                                label="Poids exact (kg)"
                                hint={
                                    heavyWithoutWeight && (
                                        <span className="text-rose-700">
                                            30 kg ou plus.
                                        </span>
                                    )
                                }
                            >
                                <input
                                    type="number"
                                    min={30}
                                    max={1000}
                                    value={form.weight ?? ''}
                                    onChange={(e) =>
                                        set(
                                            'weight',
                                            e.target.value === ''
                                                ? null
                                                : Number(e.target.value),
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>
                        )}
                        <Field label="Nombre de colis">
                            <input
                                type="number"
                                min={1}
                                max={20}
                                value={form.package_count}
                                onChange={(e) =>
                                    set(
                                        'package_count',
                                        Math.max(
                                            1,
                                            Math.min(
                                                20,
                                                Number(e.target.value) || 1,
                                            ),
                                        ),
                                    )
                                }
                                className={inputClass}
                            />
                        </Field>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border bg-muted/30 p-3">
                            <input
                                type="checkbox"
                                checked={form.fragile}
                                onChange={(e) =>
                                    set('fragile', e.target.checked)
                                }
                                className="mt-0.5 h-4 w-4 rounded border-border text-ink focus:ring-ink/20"
                            />
                            <span className="text-sm">
                                <span className="font-medium text-ink">
                                    Fragile
                                </span>
                                <span className="mt-0.5 block text-xs text-ink-soft">
                                    Valeur déclarée exigée, plus de 250 MAD.
                                </span>
                            </span>
                        </label>
                        <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border bg-muted/30 p-3">
                            <input
                                type="checkbox"
                                checked={form.allow_opening}
                                onChange={(e) =>
                                    set('allow_opening', e.target.checked)
                                }
                                className="mt-0.5 h-4 w-4 rounded border-border text-ink focus:ring-ink/20"
                            />
                            <span className="text-sm">
                                <span className="font-medium text-ink">
                                    Ouverture autorisée
                                </span>
                                <span className="mt-0.5 block text-xs text-ink-soft">
                                    Le destinataire peut ouvrir avant de payer.
                                </span>
                            </span>
                        </label>
                    </div>
                    {form.fragile && (
                        <Field
                            label="Valeur déclarée (MAD)"
                            hint={
                                fragileTooCheap && (
                                    <span className="text-rose-700">
                                        Cathedis refuse un colis fragile déclaré
                                        à 250 MAD ou moins.
                                    </span>
                                )
                            }
                        >
                            <input
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.declared_value}
                                onChange={(e) =>
                                    set(
                                        'declared_value',
                                        Number(e.target.value) || 0,
                                    )
                                }
                                className={`${inputClass} sm:w-48`}
                            />
                        </Field>
                    )}
                </section>

                <section className="space-y-3">
                    <h3 className="text-xs font-semibold tracking-wide text-ink-soft uppercase">
                        Paiement
                    </h3>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="Mode">
                            <select
                                value={form.payment_type}
                                onChange={(e) =>
                                    set('payment_type', e.target.value)
                                }
                                className={inputClass}
                            >
                                {paymentTypes.map((type) => (
                                    <option key={type} value={type}>
                                        {PAYMENT_LABEL[type] ?? type}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field
                            label="Montant à encaisser (MAD)"
                            hint="Produits et livraison compris ; 0 si déjà payé."
                        >
                            <input
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.amount}
                                onChange={(e) =>
                                    set('amount', Number(e.target.value) || 0)
                                }
                                className={inputClass}
                            />
                        </Field>
                    </div>
                    <Field label="Commentaire (imprimé sur le bon)">
                        <textarea
                            value={form.comment}
                            onChange={(e) => set('comment', e.target.value)}
                            rows={2}
                            maxLength={255}
                            placeholder="Ex. : joignable entre midi et 18 h"
                            className={`${inputClass} resize-none`}
                        />
                    </Field>
                </section>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Retour
                    </Button>
                    <Button
                        type="button"
                        disabled={
                            busy ||
                            incomplete ||
                            fragileTooCheap ||
                            heavyWithoutWeight
                        }
                        onClick={submit}
                    >
                        {busy ? 'Envoi…' : 'Envoyer à Cathedis'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
