import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    Search,
    ShoppingBag,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import SellerLayout from '@/layouts/seller/layout';
import { formatDate } from '@/lib/utils';
import type { Order, OrderStatus, PaginatedData } from '@/types';

/** Tabs mirror the statuses a seller can actually move an order through. */
const TABS: Array<{ value: OrderStatus | 'all'; label: string }> = [
    { value: 'all', label: 'Toutes' },
    { value: 'pending', label: 'En attente' },
    { value: 'confirmed', label: 'Confirmées' },
    { value: 'shipped', label: 'Expédiées' },
    { value: 'delivered', label: 'Livrées' },
    { value: 'cancelled', label: 'Annulées' },
];

const STATUS: Record<OrderStatus, { label: string; className: string }> = {
    pending: { label: 'En attente', className: 'bg-amber-100 text-amber-700' },
    confirmed: { label: 'Confirmée', className: 'bg-sky-100 text-sky-700' },
    shipped: { label: 'Expédiée', className: 'bg-violet-100 text-violet-700' },
    delivered: {
        label: 'Livrée',
        className: 'bg-emerald-100 text-emerald-700',
    },
    cancelled: { label: 'Annulée', className: 'bg-rose-100 text-rose-700' },
};

// `total_amount` arrives as a decimal string from the API, not a number.
const money = (n: string | number) =>
    `${new Intl.NumberFormat('fr-MA').format(Math.round(Number(n)))} MAD`;

export default function SellerOrders({
    orders,
}: {
    orders: PaginatedData<Order>;
}) {
    const [query, setQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<OrderStatus | 'all'>(
        'all',
    );

    /*
     * Filtering is client-side over the current page only — the controller
     * paginates without accepting filters, so this narrows what is on screen
     * rather than querying. The counts below say "sur cette page" for the same
     * reason, so the numbers are not read as totals.
     */
    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        return orders.data.filter((o) => {
            const matchesStatus =
                statusFilter === 'all' || o.status === statusFilter;
            const name =
                `${o.buyer?.first_name ?? ''} ${o.buyer?.last_name ?? ''}`.toLowerCase();
            const matchesQuery =
                !q ||
                o.reference.toLowerCase().includes(q) ||
                name.includes(q) ||
                (o.buyer?.email?.toLowerCase().includes(q) ?? false);

            return matchesStatus && matchesQuery;
        });
    }, [orders.data, query, statusFilter]);

    const countFor = (value: OrderStatus | 'all') =>
        value === 'all'
            ? orders.data.length
            : orders.data.filter((o) => o.status === value).length;

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title="Commandes" />

            <header>
                <h1 className="text-2xl font-semibold tracking-tight text-ink">
                    Commandes
                </h1>
                <p className="mt-1 text-sm text-ink-soft">
                    {orders.total} commande{orders.total > 1 ? 's' : ''} au
                    total.
                </p>
            </header>

            {/* ── Status tabs ──────────────────────────────── */}
            <div className="flex flex-wrap gap-2">
                {TABS.map((tab) => {
                    const active = statusFilter === tab.value;

                    return (
                        <button
                            key={tab.value}
                            type="button"
                            onClick={() => setStatusFilter(tab.value)}
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
                                {countFor(tab.value)}
                            </span>
                        </button>
                    );
                })}
            </div>

            {/* ── Search ───────────────────────────────────── */}
            <div className="relative max-w-md">
                <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft/50" />
                <input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Référence, client ou e-mail…"
                    className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                />
            </div>

            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Référence</TableHead>
                            <TableHead>Client</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Statut</TableHead>
                            <TableHead className="text-right">Total</TableHead>
                            <TableHead className="text-right">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filtered.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={6}
                                    className="h-40 text-center"
                                >
                                    <ShoppingBag className="mx-auto h-8 w-8 text-ink/15" />
                                    <p className="mt-3 text-sm text-ink-soft">
                                        {orders.data.length === 0
                                            ? 'Aucune commande pour le moment.'
                                            : 'Aucune commande ne correspond à ce filtre.'}
                                    </p>
                                </TableCell>
                            </TableRow>
                        ) : (
                            filtered.map((order) => {
                                const meta = STATUS[order.status] ?? {
                                    label: order.status,
                                    className: 'bg-slate-100 text-slate-700',
                                };

                                return (
                                    <TableRow key={order.id}>
                                        <TableCell>
                                            <Link
                                                href={route(
                                                    'seller.orders.show',
                                                    order.id,
                                                )}
                                                className="font-medium text-ink hover:underline"
                                            >
                                                {order.reference}
                                            </Link>
                                        </TableCell>

                                        <TableCell>
                                            {order.buyer ? (
                                                <div className="flex flex-col text-sm">
                                                    <span className="text-ink">
                                                        {order.buyer.first_name}{' '}
                                                        {order.buyer.last_name}
                                                    </span>
                                                    <span className="text-xs text-ink-soft">
                                                        {order.buyer.email ??
                                                            '—'}
                                                    </span>
                                                </div>
                                            ) : (
                                                <span className="text-sm text-ink-soft/60">
                                                    Acheteur supprimé
                                                </span>
                                            )}
                                        </TableCell>

                                        <TableCell className="text-sm text-ink-soft">
                                            {formatDate(order.created_at)}
                                        </TableCell>

                                        <TableCell>
                                            <span
                                                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${meta.className}`}
                                            >
                                                {meta.label}
                                            </span>
                                        </TableCell>

                                        <TableCell className="text-right text-sm text-ink tabular-nums">
                                            {money(order.total_amount)}
                                        </TableCell>

                                        <TableCell className="text-right">
                                            <Link
                                                href={route(
                                                    'seller.orders.show',
                                                    order.id,
                                                )}
                                                title="Voir la commande"
                                                className="inline-flex rounded-md p-2 text-ink-soft transition-colors hover:bg-muted hover:text-ink"
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                );
                            })
                        )}
                    </TableBody>
                </Table>

                {orders.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                        <p className="text-xs text-ink-soft">
                            {orders.from}–{orders.to} sur {orders.total}
                        </p>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8"
                                disabled={!orders.prev_page_url}
                                onClick={() =>
                                    router.get(
                                        orders.prev_page_url!,
                                        undefined,
                                        {
                                            preserveScroll: true,
                                        },
                                    )
                                }
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8"
                                disabled={!orders.next_page_url}
                                onClick={() =>
                                    router.get(
                                        orders.next_page_url!,
                                        undefined,
                                        {
                                            preserveScroll: true,
                                        },
                                    )
                                }
                            >
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

SellerOrders.layout = (page: ReactNode) => <SellerLayout>{page}</SellerLayout>;
