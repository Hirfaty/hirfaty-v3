import { Link, router } from '@inertiajs/react';
import { Loader2, Mail, Repeat2, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type { PaginatedData } from '@/types';

interface BrochureLead {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    type: 'buyer' | 'seller';
    seller_type: 'artist' | 'company' | 'cooperative' | null;
    locale: string | null;
    downloads_count: number;
    created_at: string;
}

/**
 * Labels are French only, like the rest of /admin — the storefront is
 * translated, the back office is not.
 */
const TYPE_LABEL: Record<BrochureLead['type'], string> = {
    buyer: 'Acheteur',
    seller: 'Vendeur',
};

const SELLER_TYPE_LABEL: Record<string, string> = {
    artist: 'Artisan',
    company: 'Entreprise',
    cooperative: 'Coopérative',
};

interface Props {
    leads: PaginatedData<BrochureLead>;
    counts: Record<string, number>;
    filters: { search?: string; type?: string };
}

const TABS = [
    { value: null, label: 'Tous', countKey: 'all' },
    { value: 'buyer', label: 'Acheteurs', countKey: 'buyer' },
    { value: 'seller', label: 'Vendeurs', countKey: 'seller' },
] as const;

const Index = ({ leads, counts, filters }: Props) => {
    const [search, setSearch] = useState(filters.search ?? '');
    const [searching, setSearching] = useState(false);
    // Skip the debounced effect's own request on first render — otherwise
    // landing with ?search= already set fires a redundant fetch.
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        setSearching(true);
        const timer = setTimeout(() => {
            router.get(
                route('admin.brochure-leads.index'),
                { ...filters, search: search || undefined, page: 1 },
                {
                    preserveState: true,
                    replace: true,
                    onFinish: () => setSearching(false),
                },
            );
        }, 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const setType = (type: string | null) => {
        router.get(
            route('admin.brochure-leads.index'),
            { ...filters, type: type ?? undefined, page: 1 },
            { preserveState: true },
        );
    };

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        Demandes de brochure
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        {counts.all ?? 0} demande{(counts.all ?? 0) > 1 ? 's' : ''}{' '}
                        au total.
                    </p>
                </div>
            </header>

            {/* ── Type tabs ────────────────────────────────── */}
            <div className="flex flex-wrap gap-2">
                {TABS.map((tab) => {
                    const active = (filters.type ?? null) === tab.value;

                    return (
                        <button
                            key={tab.label}
                            type="button"
                            onClick={() => setType(tab.value)}
                            className={`inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-medium transition ${
                                active
                                    ? 'border-ink bg-ink text-white'
                                    : 'border-border bg-card text-ink-soft hover:border-ink/30 hover:text-ink'
                            }`}
                        >
                            {tab.label}
                            <span
                                className={`rounded-full px-1.5 py-0.5 text-xs ${
                                    active
                                        ? 'bg-white/20 text-white'
                                        : 'bg-muted text-ink-soft'
                                }`}
                            >
                                {counts[tab.countKey] ?? 0}
                            </span>
                        </button>
                    );
                })}
            </div>

            {/* ── Search ───────────────────────────────────── */}
            <div className="relative max-w-md">
                {searching ? (
                    <Loader2 className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 animate-spin text-ink-soft/50" />
                ) : (
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft/50" />
                )}
                <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Nom ou e-mail…"
                    className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                />
            </div>

            {/* ── Table ────────────────────────────────────── */}
            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border text-left text-xs font-medium text-ink-soft">
                            <tr>
                                <th className="px-5 py-3">Nom</th>
                                <th className="px-5 py-3">E-mail</th>
                                <th className="px-5 py-3">Profil</th>
                                <th className="px-5 py-3">Langue</th>
                                <th className="px-5 py-3">Demandes</th>
                                <th className="px-5 py-3">Date</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {leads.data.map((lead) => (
                                <tr
                                    key={lead.id}
                                    className="transition hover:bg-muted/30"
                                >
                                    <td className="px-5 py-3 font-medium text-ink">
                                        {lead.first_name} {lead.last_name}
                                    </td>
                                    <td className="px-5 py-3">
                                        <a
                                            href={`mailto:${lead.email}`}
                                            dir="ltr"
                                            className="inline-flex items-center gap-2 text-ink-soft hover:text-ink"
                                        >
                                            <Mail className="h-3.5 w-3.5 shrink-0" />
                                            {lead.email}
                                        </a>
                                    </td>
                                    <td className="px-5 py-3">
                                        <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-ink-soft">
                                            {TYPE_LABEL[lead.type]}
                                        </span>
                                        {lead.seller_type && (
                                            <span className="ms-2 text-xs text-ink-soft">
                                                {SELLER_TYPE_LABEL[
                                                    lead.seller_type
                                                ] ?? lead.seller_type}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-5 py-3 text-ink-soft uppercase">
                                        {lead.locale ?? '—'}
                                    </td>
                                    <td className="px-5 py-3">
                                        {/* Only worth showing when they came
                                            back — one download is the default
                                            and says nothing. */}
                                        {lead.downloads_count > 1 ? (
                                            <span className="inline-flex items-center gap-1.5 font-medium text-ink">
                                                <Repeat2 className="h-3.5 w-3.5" />
                                                {lead.downloads_count}
                                            </span>
                                        ) : (
                                            <span className="text-ink-soft">
                                                1
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-5 py-3 text-ink-soft">
                                        {formatDate(lead.created_at)}
                                    </td>
                                </tr>
                            ))}

                            {leads.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-5 py-10 text-center text-ink-soft"
                                    >
                                        Aucune demande pour l’instant.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {leads.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                        <p className="text-xs text-ink-soft">
                            {leads.from}–{leads.to} sur {leads.total}
                        </p>
                        <div className="flex items-center gap-1">
                            {leads.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url ?? '#'}
                                    preserveScroll
                                    className={`flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm transition ${
                                        link.active
                                            ? 'bg-ink font-medium text-white'
                                            : 'text-ink-soft hover:bg-muted'
                                    } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

Index.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Index;
