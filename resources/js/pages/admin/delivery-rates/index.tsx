import { Head, router, useForm } from '@inertiajs/react';
import { Globe, Info, MapPin, Search } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
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

type DeliverySettings = {
    show_destinations: boolean;
    flat_fee: number;
};

const INPUT =
    'rounded-lg border border-border bg-card px-3 py-2 text-sm text-ink shadow-sm transition focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none';

const SAVE_BUTTON =
    'rounded-lg bg-ink px-4 py-2 text-sm font-medium text-card shadow-sm transition hover:bg-ink/90 disabled:opacity-50';

/** Every save on this page flashes; surface it the way the other admin pages do. */
const flashToast = {
    preserveScroll: true,
    onSuccess: (page: { props: object }) => {
        const flash = (page.props as unknown as PageProps).flash;

        if (flash?.error) {
            toast.error(flash.error);
        } else if (flash?.success) {
            toast.success(flash.success);
        }
    },
    onError: () => toast.error('Une erreur est survenue.'),
};

/**
 * The flat fee and the show-cities-and-countries switch — see
 * DeliverySettingsService. Off, checkout ships to Morocco only, asks for no
 * city, and charges the flat fee to every basket; the rate table below is
 * then kept but not used.
 */
function SettingsCard({ settings }: { settings: DeliverySettings }) {
    const form = useForm({
        flat_fee: String(settings.flat_fee),
        show_destinations: settings.show_destinations,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch(route('admin.delivery-rates.settings'), flashToast);
    };

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-5 rounded-xl border border-border bg-card p-5 shadow-sm"
        >
            <div>
                <h2 className="text-base font-semibold text-ink">
                    Paramètres de livraison
                </h2>
                <p className="mt-1 text-sm text-ink-soft">
                    Appliqués immédiatement au paiement.
                </p>
            </div>

            <label className="flex cursor-pointer items-start gap-3">
                <input
                    type="checkbox"
                    checked={form.data.show_destinations}
                    onChange={(e) =>
                        form.setData('show_destinations', e.target.checked)
                    }
                    className="mt-0.5 h-4 w-4 shrink-0 rounded border-border accent-ink"
                />
                <span>
                    <span className="block text-sm font-medium text-ink">
                        Afficher les villes et les pays au paiement
                    </span>
                    <span className="mt-0.5 block text-sm text-ink-soft">
                        Décoché : livraison au Maroc uniquement, sans choix de
                        ville, au tarif unique ci-dessous. Coché : le tarif
                        dépend de la ville ou du pays, selon la grille.
                    </span>
                </span>
            </label>

            <div className="flex flex-wrap items-end gap-3">
                <div>
                    <label
                        htmlFor="flat_fee"
                        className="mb-1.5 block text-sm font-medium text-ink"
                    >
                        Tarif unique (MAD)
                    </label>
                    <input
                        id="flat_fee"
                        type="number"
                        min="0"
                        step="0.01"
                        inputMode="decimal"
                        value={form.data.flat_fee}
                        onChange={(e) =>
                            form.setData('flat_fee', e.target.value)
                        }
                        className={`${INPUT} w-40 tabular-nums`}
                    />
                </div>

                <button
                    type="submit"
                    disabled={form.processing || !form.isDirty}
                    className={SAVE_BUTTON}
                >
                    {form.processing ? 'Enregistrement…' : 'Enregistrer'}
                </button>
            </div>

            {(form.errors.flat_fee || form.errors.show_destinations) && (
                <p className="text-sm text-destructive">
                    {form.errors.flat_fee ?? form.errors.show_destinations}
                </p>
            )}
        </form>
    );
}

/**
 * One rate line's fee, editable in place. The save button only appears once
 * the value differs from what is stored, so an untouched table stays quiet.
 */
function FeeCell({ rate }: { rate: Rate }) {
    const [value, setValue] = useState(String(rate.fee));
    const [saving, setSaving] = useState(false);

    const dirty = value.trim() !== '' && Number(value) !== rate.fee;

    const save = (e: FormEvent) => {
        e.preventDefault();

        if (!dirty) {
            return;
        }

        router.patch(
            route('admin.delivery-rates.update', rate.id),
            { fee: value },
            {
                ...flashToast,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <form onSubmit={save} className="flex items-center justify-end gap-2">
            {dirty && (
                <button
                    type="submit"
                    disabled={saving}
                    className="rounded-md bg-ink px-2.5 py-1 text-xs font-medium text-card transition hover:bg-ink/90 disabled:opacity-50"
                >
                    {saving ? '…' : 'OK'}
                </button>
            )}
            <input
                type="number"
                min="0"
                step="0.01"
                inputMode="decimal"
                aria-label={`Frais — ${rate.city_name ?? rate.country_name}`}
                value={value}
                onChange={(e) => setValue(e.target.value)}
                className={`${INPUT} w-24 py-1.5 text-right font-medium tabular-nums`}
            />
            <span className="text-xs text-ink-soft">MAD</span>
        </form>
    );
}

/**
 * The delivery service rate cards, and the delivery settings above them.
 *
 * Rows come from DeliveryRateSeeder (which only adds missing destinations,
 * so it never overwrites a fee edited here); the desk can correct any line's
 * fee but not add or delete lines. See Admin\DeliveryRateController.
 */
const Index = ({
    services,
    selectedService,
    rates,
    settings,
}: PageProps & {
    services: DeliveryService[];
    selectedService: number | null;
    rates: Rate[];
    settings: DeliverySettings;
}) => {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');

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

                {/*
                 * Segmented control to swap carrier cards. One service today
                 * renders a single button, which is fine — it stays a plain
                 * label until a second carrier exists.
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
            </header>

            {/*
             * Keyed on the stored values so a save (which reloads the props)
             * resets the form's dirty state to the new baseline.
             */}
            <SettingsCard
                key={`${settings.flat_fee}-${settings.show_destinations}`}
                settings={settings}
            />

            {/* Says which of the two pricing modes checkout is actually in,
                so an edit to the table below never looks like it did
                nothing. */}
            <div className="flex items-start gap-3 rounded-xl border border-border bg-card px-5 py-3.5 shadow-sm">
                <Info className="mt-0.5 h-4 w-4 shrink-0 text-ink-soft" />
                <p className="text-sm text-ink-soft">
                    {settings.show_destinations
                        ? `Grille ${selected?.name ?? ''} utilisée au paiement : les frais sont appliqués une seule fois par commande, selon la ville marocaine ou le pays de destination.`
                        : 'Villes et pays masqués : chaque commande paie actuellement le tarif unique ci-dessus. La grille reste modifiable et reprendra effet dès que l’affichage sera réactivé.'}
                </p>
            </div>

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

            <div className="overflow-x-auto rounded-xl border border-border bg-card shadow-sm">
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
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={4}
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
                                    <td className="px-5 py-3">
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
                                    <td className="px-5 py-3 text-ink-soft">
                                        {rate.country_name}{' '}
                                        <span className="text-ink-soft/60">
                                            ({rate.country_code})
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-ink-soft">
                                        {rate.region ?? '—'}
                                    </td>
                                    <td className="px-5 py-3">
                                        {/* Keyed on the stored fee so a
                                            save resets the cell to it. */}
                                        <FeeCell
                                            key={`${rate.id}-${rate.fee}`}
                                            rate={rate}
                                        />
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

Index.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Index;
