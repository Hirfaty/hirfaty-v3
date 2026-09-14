import { Head, Link, router } from '@inertiajs/react';
import { Mail, Repeat2 } from 'lucide-react';
import type { ReactNode } from 'react';
import AdminLayout from '@/layouts/admin/layout';
import type { PageProps } from '@/types';

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

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
    to: number | null;
}

/**
 * Labels are French only, like the rest of /admin — the storefront is
 * translated, the back office is not.
 */
const TYPE_LABELS: Record<BrochureLead['type'], string> = {
    buyer: 'Acheteur',
    seller: 'Vendeur',
};

const SELLER_TYPE_LABELS: Record<string, string> = {
    artist: 'Artisan',
    company: 'Entreprise',
    cooperative: 'Coopérative',
};

const TABS = [
    { key: '', label: 'Tous', countKey: 'all' },
    { key: 'buyer', label: 'Acheteurs', countKey: 'buyer' },
    { key: 'seller', label: 'Vendeurs', countKey: 'seller' },
] as const;

const Index = ({
    leads,
    counts,
    filters,
}: PageProps & {
    leads: Paginated<BrochureLead>;
    counts: Record<string, number>;
    filters: { search?: string; type?: string };
}) => {
    // Round-trips to the server on every change rather than filtering the page
    // in place: `leads` is one page of twenty, so a client-side filter would
    // only ever search the rows that happen to be on screen.
    const applyFilter = (next: Partial<typeof filters>) => {
        router.get(
            route('admin.brochure-leads.index'),
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );
    };

    return (
        <div className="min-h-screen bg-cream/30 p-6 md:p-10">
            <Head title="Brochure — demandes" />

            <div className="mx-auto max-w-7xl">
                <header className="mb-10">
                    <p className="font-sans text-xs font-medium tracking-[0.2em] text-ink-soft uppercase">
                        Acquisition
                    </p>
                    <h1 className="mt-2 h-editorial text-4xl text-ink md:text-5xl">
                        Demandes de <span className="italic">brochure</span>
                    </h1>
                </header>

                <div className="mb-8 flex flex-wrap items-center gap-6">
                    <div className="flex gap-2">
                        {TABS.map((tab) => {
                            const active = (filters.type ?? '') === tab.key;

                            return (
                                <button
                                    key={tab.key}
                                    onClick={() =>
                                        applyFilter({ type: tab.key })
                                    }
                                    className={`px-4 py-2 text-[11px] font-bold tracking-widest uppercase transition-colors ${
                                        active
                                            ? 'bg-ink text-cream'
                                            : 'bg-white text-ink-soft hover:text-ink'
                                    }`}
                                >
                                    {tab.label}
                                    <span className="ms-2 opacity-60">
                                        {counts[tab.countKey] ?? 0}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    <input
                        type="search"
                        defaultValue={filters.search ?? ''}
                        onChange={(e) =>
                            applyFilter({ search: e.target.value })
                        }
                        placeholder="Nom ou e-mail…"
                        className="min-w-[240px] flex-1 border border-ink/10 bg-white px-4 py-2.5 text-sm placeholder:text-ink/25 focus:border-terracotta focus:outline-none"
                    />
                </div>

                <div className="border border-ink/5 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="border-b border-ink/5 bg-ink/[0.02]">
                                    {[
                                        'Nom',
                                        'E-mail',
                                        'Profil',
                                        'Langue',
                                        'Demandes',
                                        'Date',
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            className="px-6 py-4 font-sans text-[10px] font-bold tracking-widest text-ink/40 uppercase"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-ink/5">
                                {leads.data.map((lead) => (
                                    <tr
                                        key={lead.id}
                                        className="transition-colors hover:bg-cream/10"
                                    >
                                        <td className="px-6 py-4 text-sm font-medium text-ink">
                                            {lead.first_name} {lead.last_name}
                                        </td>
                                        <td className="px-6 py-4">
                                            <a
                                                href={`mailto:${lead.email}`}
                                                dir="ltr"
                                                className="inline-flex items-center gap-2 text-sm text-ink-soft hover:text-terracotta"
                                            >
                                                <Mail className="h-3.5 w-3.5 shrink-0" />
                                                {lead.email}
                                            </a>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="text-[10px] font-semibold tracking-widest text-terracotta uppercase">
                                                {TYPE_LABELS[lead.type]}
                                            </span>
                                            {lead.seller_type && (
                                                <span className="ms-2 text-[10px] tracking-widest text-ink-soft uppercase">
                                                    {SELLER_TYPE_LABELS[
                                                        lead.seller_type
                                                    ] ?? lead.seller_type}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-xs text-ink-soft uppercase">
                                            {lead.locale ?? '—'}
                                        </td>
                                        <td className="px-6 py-4">
                                            {/* Only worth showing when they
                                                came back — one download is the
                                                default and says nothing. */}
                                            {lead.downloads_count > 1 ? (
                                                <span className="inline-flex items-center gap-1.5 text-xs font-bold text-ink">
                                                    <Repeat2 className="h-3.5 w-3.5" />
                                                    {lead.downloads_count}
                                                </span>
                                            ) : (
                                                <span className="text-xs text-ink-soft">
                                                    1
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-xs text-ink-soft">
                                            {new Date(
                                                lead.created_at,
                                            ).toLocaleDateString('fr-FR')}
                                        </td>
                                    </tr>
                                ))}

                                {leads.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-6 py-16 text-center text-sm text-ink-soft"
                                        >
                                            Aucune demande pour l’instant.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {leads.links.length > 3 && (
                    <div className="mt-8 flex flex-wrap items-center justify-between gap-4">
                        <p className="text-xs text-ink-soft">
                            {leads.from}–{leads.to} sur {leads.total}
                        </p>
                        <div className="flex gap-1">
                            {leads.links.map((link) => (
                                <Link
                                    key={link.label}
                                    href={link.url ?? '#'}
                                    preserveState
                                    className={`px-3 py-1.5 text-xs transition-colors ${
                                        link.active
                                            ? 'bg-ink text-cream'
                                            : 'bg-white text-ink-soft hover:text-ink'
                                    } ${link.url ? '' : 'pointer-events-none opacity-40'}`}
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
