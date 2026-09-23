import { Head, router } from '@inertiajs/react';
import { Globe, Info, MapPin, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
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

const money = (value: number) =>
    `${new Intl.NumberFormat('fr-MA', { maximumFractionDigits: 2 }).format(value)} MAD`;

/**
 * The delivery service rate cards, read-only.
 *
 * No add / edit / delete anywhere on this page, and no route behind one
 * either — see Admin\DeliveryRateController for why. This is a lookup table
 * the fulfilment desk consults, not settings it owns. Each carrier's card is
 * loaded by its seeder; the selector above narrows to one card once a second
 * carrier (DHL, say) makes the page need one.
 */
const Index = ({
    services,
    selectedService,
    rates,
}: PageProps & {
    services: DeliveryService[];
    selectedService: number | null;
    rates: Rate[];
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

            {/* Says plainly why there is nothing to click, so the absence of
                an "add" button reads as deliberate rather than missing. */}
            <div className="flex items-start gap-3 rounded-xl border border-border bg-card px-5 py-3.5 shadow-sm">
                <Info className="mt-0.5 h-4 w-4 shrink-0 text-ink-soft" />
                <p className="text-sm text-ink-soft">
                    Grille tarifaire fournie par {selected?.name}, en lecture
                    seule. Les frais sont appliqués une seule fois par commande,
                    selon la ville marocaine ou le pays de destination choisi au
                    paiement.
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
                                    <td className="px-5 py-3.5 text-right font-medium text-ink tabular-nums">
                                        {money(rate.fee)}
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
