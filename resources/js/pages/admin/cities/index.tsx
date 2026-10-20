import { Head, Link } from '@inertiajs/react';
import { MapPin, Search, Store, Truck } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin/layout';

/** One row of the registry — CityService::adminIndex(). */
type CityRow = {
    id: number;
    /** The French name, as `cities.name` holds it. */
    name: string;
    region: string | null;
    /** Null where no translation is on file: the storefront shows `name`. */
    translations: { ar: string | null; en: string | null };
    stores_count: number;
};

type DistributionRow = {
    id: number;
    name: string;
    stores_count: number;
};

interface Props {
    cities: CityRow[];
    /** The cities with the most shops, most first — already ranked and capped. */
    distribution: DistributionRow[];
    totalStores: number;
}

/** Case- and accent-blind, so "fes" finds "Fès". */
const normalize = (value: string) =>
    value
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();

function MissingTranslation() {
    return (
        <span className="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">
            Non traduit
        </span>
    );
}

/**
 * Shops per city as horizontal bars: one series, so one colour, with the
 * count at each bar's tip. The table below is the full, exact view.
 */
function StoreDistribution({
    distribution,
    totalStores,
    citiesWithStores,
}: {
    distribution: DistributionRow[];
    totalStores: number;
    citiesWithStores: number;
}) {
    const max = Math.max(...distribution.map((city) => city.stores_count), 1);

    return (
        <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-3.5">
                <div className="flex items-center gap-2">
                    <Store className="h-4 w-4 text-ink-soft" />
                    <h2 className="text-sm font-semibold text-ink">
                        Répartition des boutiques par ville
                    </h2>
                </div>
                {citiesWithStores > distribution.length && (
                    <span className="text-xs text-ink-soft">
                        {distribution.length} premières villes sur{' '}
                        {citiesWithStores}
                    </span>
                )}
            </div>

            {distribution.length === 0 ? (
                <p className="px-5 py-10 text-center text-sm text-ink-soft">
                    Aucune boutique n'est encore rattachée à une ville.
                </p>
            ) : (
                <ul className="flex flex-col gap-0.5 p-3">
                    {distribution.map((city) => {
                        const share = Math.round(
                            (city.stores_count / Math.max(totalStores, 1)) *
                                100,
                        );

                        return (
                            <li key={city.id}>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <div className="group grid cursor-default grid-cols-[6rem_1fr] items-center gap-3 rounded-md px-2 py-1.5 transition-colors hover:bg-muted/40 sm:grid-cols-[9rem_1fr] sm:gap-4">
                                            <span className="truncate text-sm text-ink">
                                                {city.name}
                                            </span>
                                            {/* End padding keeps room for the
                                                count beside the longest bar;
                                                logical sides so the bar still
                                                grows from the start under
                                                `dir="rtl"`. */}
                                            <div className="flex items-center gap-2 pe-10">
                                                <div
                                                    className="h-[18px] shrink-0 rounded-e-[4px] bg-terracotta transition-opacity group-hover:opacity-80"
                                                    style={{
                                                        width: `${(city.stores_count / max) * 100}%`,
                                                    }}
                                                />
                                                <span className="text-sm font-medium text-ink tabular-nums">
                                                    {city.stores_count}
                                                </span>
                                            </div>
                                        </div>
                                    </TooltipTrigger>
                                    <TooltipContent side="top">
                                        {city.name} · {city.stores_count}{' '}
                                        boutique
                                        {city.stores_count > 1
                                            ? 's'
                                            : ''} · {share} % du total
                                    </TooltipContent>
                                </Tooltip>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

export default function CitiesIndex({
    cities,
    distribution,
    totalStores,
}: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');

    const needle = normalize(search.trim());
    const visible = needle
        ? cities.filter((city) =>
              [city.name, city.translations.ar, city.translations.en]
                  .filter((name): name is string => !!name)
                  .some((name) => normalize(name).includes(needle)),
          )
        : cities;
    const citiesWithStores = cities.filter(
        (city) => city.stores_count > 0,
    ).length;

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title={t('titles.admin.cities')} />
            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        Villes
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        {cities.length} ville{cities.length > 1 ? 's' : ''} ·{' '}
                        {totalStores} boutique{totalStores > 1 ? 's' : ''}{' '}
                        rattachée{totalStores > 1 ? 's' : ''}.
                    </p>
                </div>
                {/* Cities are added along with their delivery rate. */}
                <Link
                    href={route('admin.delivery-rates.index')}
                    className="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2.5 text-sm font-medium text-ink shadow-sm transition hover:border-ink/30"
                >
                    <Truck className="h-4 w-4" />
                    Tarifs de livraison
                </Link>
            </header>

            <StoreDistribution
                distribution={distribution}
                totalStores={totalStores}
                citiesWithStores={citiesWithStores}
            />

            {/* ── Search ───────────────────────────────────── */}
            <div className="relative max-w-md">
                <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft/50" />
                <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Ville, en français, arabe ou anglais…"
                    className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                />
            </div>

            {/* ── Table ────────────────────────────────────── */}
            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Ville</TableHead>
                            <TableHead>Arabe</TableHead>
                            <TableHead>Anglais</TableHead>
                            <TableHead className="text-right">
                                Boutiques
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {visible.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="h-32 text-center text-ink-soft"
                                >
                                    Aucune ville ne correspond à cette
                                    recherche.
                                </TableCell>
                            </TableRow>
                        ) : (
                            visible.map((city) => (
                                <TableRow key={city.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted text-ink-soft">
                                                <MapPin className="h-4 w-4" />
                                            </div>
                                            <div className="flex flex-col">
                                                <span className="font-medium text-ink">
                                                    {city.name}
                                                </span>
                                                {city.region && (
                                                    <span className="text-xs text-ink-soft">
                                                        {city.region}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {city.translations.ar ? (
                                            <span
                                                dir="rtl"
                                                lang="ar"
                                                className="text-sm text-ink"
                                            >
                                                {city.translations.ar}
                                            </span>
                                        ) : (
                                            <MissingTranslation />
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {city.translations.en ? (
                                            <span className="text-sm text-ink">
                                                {city.translations.en}
                                            </span>
                                        ) : (
                                            <MissingTranslation />
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {city.stores_count > 0 ? (
                                            <span className="inline-flex items-center gap-1.5 text-sm text-ink tabular-nums">
                                                <Store className="h-3.5 w-3.5 text-ink-soft" />
                                                {city.stores_count}
                                            </span>
                                        ) : (
                                            <span className="text-sm text-ink-soft/60">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}

CitiesIndex.layout = (page: ReactNode) => <AdminLayout children={page} />;
