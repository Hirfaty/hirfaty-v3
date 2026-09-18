import { Link, router } from '@inertiajs/react';
import { Loader2, Mail, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type { PaginatedData } from '@/types';

interface NewsletterSubscriber {
    id: number;
    email: string;
    locale: string | null;
    created_at: string;
}

interface Props {
    subscribers: PaginatedData<NewsletterSubscriber>;
    filters: { search?: string };
}

const Index = ({ subscribers, filters }: Props) => {
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
                route('admin.newsletter-subscribers.index'),
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

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        Newsletter
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        {subscribers.total} abonné
                        {subscribers.total > 1 ? 's' : ''} au total.
                    </p>
                </div>
            </header>

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
                    placeholder="E-mail…"
                    className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                />
            </div>

            {/* ── Table ────────────────────────────────────── */}
            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border text-left text-xs font-medium text-ink-soft">
                            <tr>
                                <th className="px-5 py-3">E-mail</th>
                                <th className="px-5 py-3">Langue</th>
                                <th className="px-5 py-3">Date</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {subscribers.data.map((subscriber) => (
                                <tr
                                    key={subscriber.id}
                                    className="transition hover:bg-muted/30"
                                >
                                    <td className="px-5 py-3">
                                        <a
                                            href={`mailto:${subscriber.email}`}
                                            dir="ltr"
                                            className="inline-flex items-center gap-2 text-ink-soft hover:text-ink"
                                        >
                                            <Mail className="h-3.5 w-3.5 shrink-0" />
                                            {subscriber.email}
                                        </a>
                                    </td>
                                    <td className="px-5 py-3 text-ink-soft uppercase">
                                        {subscriber.locale ?? '—'}
                                    </td>
                                    <td className="px-5 py-3 text-ink-soft">
                                        {formatDate(subscriber.created_at)}
                                    </td>
                                </tr>
                            ))}

                            {subscribers.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={3}
                                        className="px-5 py-10 text-center text-ink-soft"
                                    >
                                        Aucun abonné pour l’instant.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {subscribers.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                        <p className="text-xs text-ink-soft">
                            {subscribers.from}–{subscribers.to} sur{' '}
                            {subscribers.total}
                        </p>
                        <div className="flex items-center gap-1">
                            {subscribers.links.map((link, i) => (
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
