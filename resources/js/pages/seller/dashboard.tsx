import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Clock,
    Coins,
    Eye,
    Package,
    ShoppingCart,
    TrendingUp,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import SellerLayout from '@/layouts/seller/layout';
import { formatDate } from '@/lib/utils';

interface Store {
    id: number;
    store_name: string;
    description: string | null;
}

interface Stats {
    total_revenue: number;
    pending_payout: number;
    total_orders: number;
    orders_today: number;
    orders_week: number;
    orders_month: number;
    total_products: number;
    published: number;
    pending_review: number;
    total_views: number;
}

interface RevenueChartItem {
    label: string;
    total: number;
}

interface RecentOrder {
    id: number;
    /** Public-facing identifier — display this rather than `id`. */
    reference: string;
    status: string;
    total: number;
    created_at: string;
    buyer: { first_name: string; last_name: string } | null;
    items: Array<{
        id: number;
        quantity: number;
        product: { name: string } | null;
    }>;
}

interface TopProduct {
    id: number;
    name: string;
    price: number;
    order_items_count: number;
    images: Array<{ url: string }>;
}

interface DashboardProps {
    store: Store;
    stats: Stats;
    revenueChart: RevenueChartItem[];
    orderBreakdown: Record<string, number>;
    recentOrders: RecentOrder[];
    topProducts: TopProduct[];
}

const ORDER_STATUS: Record<string, { label: string; className: string }> = {
    pending: { label: 'En attente', className: 'bg-amber-100 text-amber-700' },
    confirmed: { label: 'Confirmée', className: 'bg-sky-100 text-sky-700' },
    shipped: { label: 'Expédiée', className: 'bg-violet-100 text-violet-700' },
    delivered: {
        label: 'Livrée',
        className: 'bg-emerald-100 text-emerald-700',
    },
    cancelled: { label: 'Annulée', className: 'bg-rose-100 text-rose-700' },
};

const money = (n: number) =>
    `${new Intl.NumberFormat('fr-MA').format(Math.round(n))} MAD`;

export default function SellerDashboard({
    store,
    stats,
    revenueChart,
    orderBreakdown,
    recentOrders,
    topProducts,
}: DashboardProps) {
    // Scale the bars against the tallest month so a flat run of small months
    // still reads as a chart rather than a row of slivers.
    const peak = Math.max(...revenueChart.map((r) => r.total), 1);

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title="Tableau de bord" />

            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        {store.store_name}
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        Votre activité en un coup d’œil.
                    </p>
                </div>
                <Link
                    href={route('seller.orders.index')}
                    className="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2.5 text-sm font-medium text-ink-soft transition hover:border-ink/30 hover:text-ink"
                >
                    Voir les commandes
                    <ArrowRight className="h-4 w-4 rtl:rotate-180" />
                </Link>
            </header>

            {/* ── Headline figures ─────────────────────────── */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    Icon={Coins}
                    label="Total encaissé"
                    value={money(stats.total_revenue)}
                    hint={`${money(stats.pending_payout)} en attente de versement`}
                    accent="text-emerald-600 bg-emerald-50"
                />
                <StatCard
                    Icon={ShoppingCart}
                    label="Commandes"
                    value={String(stats.total_orders)}
                    hint={`${stats.orders_today} aujourd’hui · ${stats.orders_month} ce mois`}
                    accent="text-sky-600 bg-sky-50"
                />
                <StatCard
                    Icon={Package}
                    label="Produits"
                    value={String(stats.total_products)}
                    hint={`${stats.published} en ligne · ${stats.pending_review} en revue`}
                    accent="text-violet-600 bg-violet-50"
                />
                <StatCard
                    Icon={Eye}
                    label="Vues produits"
                    value={new Intl.NumberFormat('fr-MA').format(
                        stats.total_views,
                    )}
                    hint="Depuis la création de l’atelier"
                    accent="text-amber-600 bg-amber-50"
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* ── Revenue chart ────────────────────────── */}
                <Panel
                    className="lg:col-span-2"
                    title="Revenus"
                    hint="Six derniers mois"
                    Icon={TrendingUp}
                >
                    {revenueChart.length === 0 ? (
                        <Empty>Aucun revenu enregistré pour l’instant.</Empty>
                    ) : (
                        <div className="flex h-52 items-end gap-3 pt-2">
                            {revenueChart.map((row) => (
                                <div
                                    key={row.label}
                                    className="flex flex-1 flex-col items-center gap-2"
                                >
                                    <span className="text-[11px] text-ink-soft tabular-nums">
                                        {Math.round(row.total)}
                                    </span>
                                    <div
                                        className="w-full rounded-t-md bg-ink/80 transition-all"
                                        style={{
                                            height: `${Math.max((row.total / peak) * 100, 2)}%`,
                                        }}
                                    />
                                    <span className="text-xs text-ink-soft">
                                        {row.label}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </Panel>

                {/* ── Order breakdown ──────────────────────── */}
                <Panel title="Répartition" hint="Par statut" Icon={Clock}>
                    {Object.keys(orderBreakdown).length === 0 ? (
                        <Empty>Aucune commande pour l’instant.</Empty>
                    ) : (
                        <ul className="space-y-3">
                            {Object.entries(orderBreakdown).map(
                                ([status, count]) => {
                                    const meta = ORDER_STATUS[status] ?? {
                                        label: status,
                                        className:
                                            'bg-slate-100 text-slate-700',
                                    };

                                    return (
                                        <li
                                            key={status}
                                            className="flex items-center justify-between gap-3"
                                        >
                                            <span
                                                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${meta.className}`}
                                            >
                                                {meta.label}
                                            </span>
                                            <span className="text-sm text-ink tabular-nums">
                                                {count}
                                            </span>
                                        </li>
                                    );
                                },
                            )}
                        </ul>
                    )}
                </Panel>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                {/* ── Recent orders ────────────────────────── */}
                <Panel
                    title="Dernières commandes"
                    Icon={ShoppingCart}
                    action={
                        <Link
                            href={route('seller.orders.index')}
                            className="text-sm text-ink-soft transition-colors hover:text-ink"
                        >
                            Tout voir
                        </Link>
                    }
                >
                    {recentOrders.length === 0 ? (
                        <Empty>Aucune commande pour l’instant.</Empty>
                    ) : (
                        <ul className="divide-y divide-border">
                            {recentOrders.map((order) => {
                                const meta = ORDER_STATUS[order.status] ?? {
                                    label: order.status,
                                    className: 'bg-slate-100 text-slate-700',
                                };

                                return (
                                    <li key={order.id}>
                                        <Link
                                            href={route(
                                                'seller.orders.show',
                                                order.id,
                                            )}
                                            className="flex items-center justify-between gap-3 py-3 transition-colors hover:bg-muted/40"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-ink">
                                                    {order.reference}
                                                </p>
                                                <p className="mt-0.5 truncate text-xs text-ink-soft">
                                                    {order.buyer
                                                        ? `${order.buyer.first_name} ${order.buyer.last_name}`
                                                        : 'Acheteur supprimé'}
                                                    {' · '}
                                                    {formatDate(
                                                        order.created_at,
                                                    )}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-3">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${meta.className}`}
                                                >
                                                    {meta.label}
                                                </span>
                                                <span className="text-sm text-ink tabular-nums">
                                                    {money(order.total)}
                                                </span>
                                            </div>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </Panel>

                {/* ── Top products ─────────────────────────── */}
                <Panel
                    title="Produits les plus vendus"
                    Icon={CheckCircle2}
                    action={
                        <Link
                            href={route('seller.products.index')}
                            className="text-sm text-ink-soft transition-colors hover:text-ink"
                        >
                            Tout voir
                        </Link>
                    }
                >
                    {topProducts.length === 0 ? (
                        <Empty>Aucune vente pour l’instant.</Empty>
                    ) : (
                        <ul className="divide-y divide-border">
                            {topProducts.map((product) => (
                                <li
                                    key={product.id}
                                    className="flex items-center gap-3 py-3"
                                >
                                    <div className="h-11 w-11 shrink-0 overflow-hidden rounded-lg bg-muted">
                                        {product.images?.[0] && (
                                            <img
                                                src={product.images[0].url}
                                                alt=""
                                                className="h-full w-full object-cover"
                                            />
                                        )}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-ink">
                                            {product.name}
                                        </p>
                                        <p className="mt-0.5 text-xs text-ink-soft">
                                            {product.order_items_count} vendu
                                            {product.order_items_count > 1
                                                ? 's'
                                                : ''}
                                        </p>
                                    </div>
                                    <span className="shrink-0 text-sm text-ink tabular-nums">
                                        {money(product.price)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </div>
    );
}

function StatCard({
    Icon,
    label,
    value,
    hint,
    accent,
}: {
    Icon: ComponentType<{ className?: string }>;
    label: string;
    value: string;
    hint: string;
    accent: string;
}) {
    return (
        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <p className="text-sm text-ink-soft">{label}</p>
                <span
                    className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${accent}`}
                >
                    <Icon className="h-4 w-4" />
                </span>
            </div>
            <p className="mt-3 text-2xl font-semibold tracking-tight text-ink tabular-nums">
                {value}
            </p>
            <p className="mt-1 text-xs text-ink-soft">{hint}</p>
        </div>
    );
}

function Panel({
    title,
    hint,
    Icon,
    action,
    className,
    children,
}: {
    title: string;
    hint?: string;
    Icon: ComponentType<{ className?: string }>;
    action?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section
            className={`rounded-xl border border-border bg-card p-5 shadow-sm ${className ?? ''}`}
        >
            <div className="mb-4 flex items-center justify-between gap-3">
                <div className="flex items-center gap-2.5">
                    <Icon className="h-4 w-4 text-ink-soft" />
                    <div>
                        <h2 className="text-sm font-semibold text-ink">
                            {title}
                        </h2>
                        {hint && (
                            <p className="text-xs text-ink-soft">{hint}</p>
                        )}
                    </div>
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}

function Empty({ children }: { children: ReactNode }) {
    return <p className="py-8 text-center text-sm text-ink-soft">{children}</p>;
}

SellerDashboard.layout = (page: ReactNode) => (
    <SellerLayout>{page}</SellerLayout>
);
