import { Link, router } from '@inertiajs/react';
import { ArrowUpRight, ImageOff, Loader2, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type { OrderStatus } from '@/types';

const STATUS_BADGE: Record<OrderStatus, string> = {
    pending: 'bg-slate-100 text-slate-700',
    confirmed: 'bg-amber-100 text-amber-700',
    shipped: 'bg-blue-100 text-blue-700',
    delivered: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-rose-100 text-rose-700',
};

const STATUS_LABEL: Record<OrderStatus, string> = {
    pending: 'En attente',
    confirmed: 'Confirmée',
    shipped: 'Expédiée',
    delivered: 'Livrée',
    cancelled: 'Annulée',
};

const currency = (value: number) =>
    new Intl.NumberFormat('fr-MA', {
        style: 'currency',
        currency: 'MAD',
    }).format(value);

/** One checkout, as Admin\OrderController::summariseGroup shapes it. */
export type OrderGroup = {
    reference: string;
    primary_reference: string;
    created_at: string;
    buyer: { name: string; email: string | null };
    shipping_address: string | null;
    shipping_phone: string | null;
    city_name: string | null;
    notes: string | null;
    products_total: number;
    delivery_total: number;
    /** Null when the workshops' parcels have been moved apart. */
    status: OrderStatus | null;
    tracking_numbers: string[];
    orders: {
        id: number;
        reference: string;
        status: OrderStatus;
        tracking_number: string | null;
        total_amount: number;
        amana_fee: number;
        store: { id: number | null; name: string };
        items: {
            id: number;
            name: string;
            variant_label: string | null;
            quantity: number;
            subtotal: number;
            image: string | null;
        }[];
    }[];
};

type Pagination = {
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

/** The first few pieces in a purchase, as thumbnails. */
function ItemThumbs({ group }: { group: OrderGroup }) {
    const items = group.orders.flatMap((order) => order.items);
    const shown = items.slice(0, 4);
    const extra = items.length - shown.length;

    return (
        <div className="flex items-center gap-1.5">
            {shown.map((item) => (
                <span
                    key={item.id}
                    title={item.name}
                    className="h-10 w-10 shrink-0 overflow-hidden rounded-md border border-border bg-muted"
                >
                    {item.image ? (
                        <img
                            src={item.image}
                            alt={item.name}
                            loading="lazy"
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <span className="flex h-full w-full items-center justify-center">
                            <ImageOff className="h-4 w-4 text-ink-soft/40" />
                        </span>
                    )}
                </span>
            ))}
            {extra > 0 && (
                <span className="text-xs text-ink-soft">+{extra}</span>
            )}
        </div>
    );
}

/**
 * The fulfilment desk's queue.
 *
 * One row per *purchase*, not per order row: checkout splits a basket into an
 * order per workshop so each artisan is paid separately, but the desk collects
 * and delivers the lot as one job. See Admin\OrderController.
 */
const Index = ({
    groups,
    pagination,
    filters,
}: {
    groups: OrderGroup[];
    pagination: Pagination;
    filters: { search?: string; status?: string };
}) => {
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
                route('admin.orders.index'),
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

    const setStatus = (status: string | null) => {
        router.get(
            route('admin.orders.index'),
            { ...filters, status: status ?? undefined, page: 1 },
            { preserveState: true },
        );
    };

    const tabs = [
        { value: null, label: 'Toutes' },
        { value: 'pending', label: 'En attente' },
        { value: 'confirmed', label: 'Confirmées' },
        { value: 'shipped', label: 'Expédiées' },
        { value: 'delivered', label: 'Livrées' },
        { value: 'cancelled', label: 'Annulées' },
    ] as const;

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        Commandes
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        {groups.length} achat{groups.length > 1 ? 's' : ''} sur
                        cette page · {pagination.total} commande
                        {pagination.total > 1 ? 's' : ''} au total
                    </p>
                </div>
            </header>

            {/* ── Status tabs ──────────────────────────────── */}
            <div className="flex flex-wrap gap-2">
                {tabs.map((tab) => {
                    const active = (filters.status ?? null) === tab.value;

                    return (
                        <button
                            key={tab.label}
                            type="button"
                            onClick={() => setStatus(tab.value)}
                            className={`inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-medium transition ${
                                active
                                    ? 'border-ink bg-ink text-white'
                                    : 'border-border bg-card text-ink-soft hover:border-ink/30 hover:text-ink'
                            }`}
                        >
                            {tab.label}
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
                    placeholder="Référence, suivi, acheteur ou atelier…"
                    className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                />
            </div>

            {/* ── Table ────────────────────────────────────── */}
            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border text-left text-xs font-medium text-ink-soft">
                            <tr>
                                <th className="px-5 py-3">Achat</th>
                                <th className="px-5 py-3">Client</th>
                                <th className="px-5 py-3">Articles</th>
                                <th className="px-5 py-3">Ateliers</th>
                                <th className="px-5 py-3">Montant</th>
                                <th className="px-5 py-3">Date</th>
                                <th className="px-5 py-3">Statut</th>
                                <th className="px-5 py-3 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {groups.map((group) => (
                                <tr
                                    key={group.reference}
                                    className="transition hover:bg-muted/30"
                                >
                                    <td className="px-5 py-3">
                                        <p className="font-medium text-ink">
                                            {group.reference}
                                        </p>
                                        {group.tracking_numbers.length > 0 && (
                                            <p className="text-xs text-ink-soft">
                                                Suivi :{' '}
                                                {group.tracking_numbers.join(
                                                    ', ',
                                                )}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-5 py-3">
                                        <p className="font-medium text-ink">
                                            {group.buyer.name}
                                        </p>
                                        {group.city_name && (
                                            <p className="text-xs text-ink-soft">
                                                {group.city_name}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-5 py-3">
                                        <ItemThumbs group={group} />
                                    </td>
                                    <td className="px-5 py-3">
                                        <p className="text-ink">
                                            {group.orders
                                                .map((o) => o.store.name)
                                                .join(', ')}
                                        </p>
                                    </td>
                                    <td className="px-5 py-3">
                                        <p className="font-medium text-ink">
                                            {currency(
                                                group.products_total +
                                                    group.delivery_total,
                                            )}
                                        </p>
                                        <p className="text-xs text-ink-soft">
                                            Livraison :{' '}
                                            {currency(group.delivery_total)}
                                        </p>
                                    </td>
                                    <td className="px-5 py-3 text-ink-soft">
                                        {formatDate(group.created_at)}
                                    </td>
                                    <td className="px-5 py-3">
                                        {group.status ? (
                                            <span
                                                className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_BADGE[group.status]}`}
                                            >
                                                {STATUS_LABEL[group.status]}
                                            </span>
                                        ) : (
                                            /* The workshops' parcels have been
                                               moved apart — see the "handle
                                               separately" box on the detail
                                               page. Showing one of their
                                               statuses here would misreport
                                               the others. */
                                            <span className="inline-flex items-center rounded-full bg-violet-100 px-2.5 py-1 text-xs font-medium text-violet-700">
                                                Séparées
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-5 py-3 text-right">
                                        <Link
                                            href={route(
                                                'admin.orders.show',
                                                group.primary_reference,
                                            )}
                                            aria-label={`Voir l’achat ${group.reference}`}
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-ink-soft transition hover:bg-muted hover:text-ink"
                                        >
                                            <ArrowUpRight className="h-4 w-4" />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {groups.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-5 py-10 text-center text-ink-soft"
                                    >
                                        Aucune commande trouvée.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {pagination.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                        <p className="text-xs text-ink-soft">
                            Page {pagination.current_page} sur{' '}
                            {pagination.last_page}
                        </p>
                        <div className="flex items-center gap-1">
                            {pagination.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url || '#'}
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
