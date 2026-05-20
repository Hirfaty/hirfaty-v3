// import SellerLayout from '@/layouts/seller/layout';

// export default function SellerDashboard() {
//     return (
//         <main className="p-margin-page w-full flex-grow md:ml-72">
//             <div className="mb-gutter flex items-end justify-between">
//                 <div>
//                     <h1 className="font-h1 text-h1 text-on-surface">
//                         Seller Overview
//                     </h1>
//                     <p className="text-body-lg text-on-surface-variant">
//                         Manage your artisan creations and track store
//                         performance.
//                     </p>
//                 </div>
//                 <div className="gap-stack-sm hidden items-center sm:flex">
//                     <button className="px-stack-lg border-outline font-label-md text-label-md hover:bg-surface-container-low rounded-lg border border-terracotta bg-white py-2 text-secondary transition-all">
//                         Export Report
//                     </button>
//                     <button className="px-stack-lg font-label-md text-label-md rounded-lg bg-primary py-2 text-white shadow-sm transition-all hover:opacity-90">
//                         + Add Product
//                     </button>
//                 </div>
//             </div>
//         </main>
//     );
// }

// SellerDashboard.layout = (page: React.ReactNode) => (
//     <SellerLayout>{page}</SellerLayout>
// );

import { Link } from '@inertiajs/react';
import {
    BarChart3,
    TrendingUp,
    Package,
    ShoppingCart,
    Eye,
    AlertCircle,
    CheckCircle2,
    Clock,
} from 'lucide-react';
import SellerLayout from '@/layouts/seller/layout';
import { Head } from '@inertiajs/react';
import { useEffect } from 'react';

interface Store {
    id: number;
    store_name: string;
    description: string;
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

interface OrderItem {
    id: number;
    quantity: number;
    product: {
        name: string;
    };
}

interface RecentOrder {
    id: number;
    status: string;
    total: number;
    created_at: string;
    buyer: {
        first_name: string;
        last_name: string;
    };
    items: OrderItem[];
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

function formatMAD(value: number) {
    return new Intl.NumberFormat('fr-MA', {
        style: 'currency',
        currency: 'MAD',
    }).format(value);
}

function StatCard({
    icon: Icon,
    label,
    value,
    subtext,
}: {
    icon: React.ReactNode;
    label: string;
    value: string | number;
    subtext?: string;
}) {
    return (
        <div className="border border-border bg-card p-6">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p className="mt-2 text-2xl font-semibold text-foreground">
                        {value}
                    </p>
                    {subtext && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            {subtext}
                        </p>
                    )}
                </div>
                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    {Icon}
                </div>
            </div>
        </div>
    );
}

export default function Dashboard({
    store,
    stats,
    revenueChart,
    orderBreakdown,
    recentOrders,
    topProducts,
}: DashboardProps) {
    const maxRevenue = Math.max(...revenueChart.map((item) => item.total), 1);

    return (
        <>
            <Head title="Seller Dashboard" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="mb-8">
                    <p className="text-xs font-semibold tracking-[0.3em] text-muted-foreground uppercase">
                        Tableau de bord
                    </p>
                    <h1 className="font-display text-3xl tracking-tight text-foreground sm:text-4xl">
                        Bienvenue, {store.store_name}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Suivez vos ventes, commandes et performance.
                    </p>
                </div>

                {/* Stats Grid */}
                <div className="mb-8 grid gap-6 md:grid-cols-2 lg:grid-cols-5">
                    <StatCard
                        icon={<TrendingUp className="h-6 w-6 text-green-600" />}
                        label="Revenus totaux"
                        value={formatMAD(stats.total_revenue)}
                    />
                    <StatCard
                        icon={
                            <AlertCircle className="h-6 w-6 text-amber-600" />
                        }
                        label="Payout en attente"
                        value={formatMAD(stats.pending_payout)}
                    />
                    <StatCard
                        icon={
                            <ShoppingCart className="h-6 w-6 text-blue-600" />
                        }
                        label="Total commandes"
                        value={stats.total_orders}
                        subtext={`${stats.orders_today} aujourd'hui`}
                    />
                    <StatCard
                        icon={<Package className="h-6 w-6 text-purple-600" />}
                        label="Published Produits"
                        value={stats.published}
                        subtext={`${stats.pending_review} en attente`}
                    />
                    <StatCard
                        icon={<Eye className="h-6 w-6 text-pink-600" />}
                        label="Vues produits"
                        value={stats.total_views}
                    />
                </div>

                {/* Charts and Tables Grid */}
                <div className="grid gap-8 lg:grid-cols-3">
                    {/* Revenue Chart */}
                    <div className="rounded-lg border border-border bg-card p-6 lg:col-span-2">
                        <div className="mb-6 flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-foreground">
                                Revenus (6 derniers mois)
                            </h2>
                            <BarChart3 className="h-5 w-5 text-muted-foreground" />
                        </div>

                        <div className="flex h-64 items-end justify-between gap-2">
                            {revenueChart.map((item, idx) => (
                                <div
                                    key={idx}
                                    className="flex flex-1 flex-col items-center gap-2"
                                >
                                    <div className="w-full">
                                        <div
                                            className="w-full rounded-sm bg-gradient-to-t from-blue-500 to-blue-400 transition-all hover:from-blue-600 hover:to-blue-500"
                                            style={{
                                                height: `${(item.total / maxRevenue) * 100}%`,
                                                minHeight:
                                                    item.total > 0
                                                        ? '8px'
                                                        : '0',
                                            }}
                                            title={formatMAD(item.total)}
                                        />
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {item.label}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Order Status Breakdown */}
                    <div className="rounded-lg border border-border bg-card p-6">
                        <h2 className="mb-6 text-lg font-semibold text-foreground">
                            Statut des commandes
                        </h2>

                        <div className="space-y-4">
                            {Object.entries(orderBreakdown).map(
                                ([status, count]) => (
                                    <div key={status}>
                                        <div className="mb-1 flex items-center justify-between">
                                            <span className="text-sm text-muted-foreground capitalize">
                                                {status === 'pending'
                                                    ? 'En attente'
                                                    : status === 'confirmed'
                                                      ? 'Confirmée'
                                                      : status === 'shipped'
                                                        ? 'Expédiée'
                                                        : status === 'delivered'
                                                          ? 'Livrée'
                                                          : status ===
                                                              'cancelled'
                                                            ? 'Annulée'
                                                            : status}
                                            </span>
                                            <span className="font-semibold text-foreground">
                                                {count}
                                            </span>
                                        </div>
                                        <div className="h-2 w-full rounded-full bg-muted">
                                            <div
                                                className={`h-full rounded-full transition-all ${
                                                    status === 'pending'
                                                        ? 'bg-amber-500'
                                                        : status === 'confirmed'
                                                          ? 'bg-blue-500'
                                                          : status === 'shipped'
                                                            ? 'bg-purple-500'
                                                            : status ===
                                                                'delivered'
                                                              ? 'bg-green-500'
                                                              : status ===
                                                                  'cancelled'
                                                                ? 'bg-red-500'
                                                                : 'bg-gray-500'
                                                }`}
                                                style={{
                                                    width: `${(count / Math.max(...Object.values(orderBreakdown), 1)) * 100}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>
                    </div>
                </div>

                {/* Recent Orders */}
                <div className="mt-8 rounded-lg border border-border bg-card p-6">
                    <div className="mb-6 flex items-center justify-between">
                        <h2 className="text-lg font-semibold text-foreground">
                            Commandes récentes
                        </h2>
                        <Link
                            href={route('seller.orders.index')}
                            className="text-sm text-blue-600 hover:text-blue-700"
                        >
                            Voir tout →
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold text-foreground">
                                        Commande
                                    </th>
                                    <th className="px-4 py-3 text-left font-semibold text-foreground">
                                        Client
                                    </th>
                                    <th className="px-4 py-3 text-left font-semibold text-foreground">
                                        Montant
                                    </th>
                                    <th className="px-4 py-3 text-left font-semibold text-foreground">
                                        Statut
                                    </th>
                                    <th className="px-4 py-3 text-left font-semibold text-foreground">
                                        Date
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentOrders.length > 0 ? (
                                    recentOrders.map((order) => (
                                        <tr
                                            key={order.id}
                                            className="border-b border-border/50 hover:bg-muted/50"
                                        >
                                            <td className="px-4 py-3 text-foreground">
                                                <Link
                                                    href={route(
                                                        'seller.orders.show',
                                                        order.id,
                                                    )}
                                                    className="font-medium text-blue-600 hover:text-blue-700"
                                                >
                                                    #{order.id}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {order.buyer.first_name}{' '}
                                                {order.buyer.last_name}
                                            </td>
                                            <td className="px-4 py-3 font-semibold text-foreground">
                                                {formatMAD(order.total)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ${
                                                        order.status ===
                                                        'pending'
                                                            ? 'bg-amber-100 text-amber-800'
                                                            : order.status ===
                                                                'confirmed'
                                                              ? 'bg-blue-100 text-blue-800'
                                                              : order.status ===
                                                                  'shipped'
                                                                ? 'bg-purple-100 text-purple-800'
                                                                : order.status ===
                                                                    'delivered'
                                                                  ? 'bg-green-100 text-green-800'
                                                                  : 'bg-red-100 text-red-800'
                                                    }`}
                                                >
                                                    {order.status ===
                                                    'pending' ? (
                                                        <Clock className="h-3 w-3" />
                                                    ) : order.status ===
                                                      'delivered' ? (
                                                        <CheckCircle2 className="h-3 w-3" />
                                                    ) : null}
                                                    {order.status === 'pending'
                                                        ? 'En attente'
                                                        : order.status ===
                                                            'confirmed'
                                                          ? 'Confirmée'
                                                          : order.status ===
                                                              'shipped'
                                                            ? 'Expédiée'
                                                            : order.status ===
                                                                'delivered'
                                                              ? 'Livrée'
                                                              : 'Annulée'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {new Date(
                                                    order.created_at,
                                                ).toLocaleDateString('fr-FR')}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            Aucune commande pour le moment
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Top Products */}
                <div className="mt-8 rounded-lg border border-border bg-card p-6">
                    <div className="mb-6 flex items-center justify-between">
                        <h2 className="text-lg font-semibold text-foreground">
                            Produits populaires
                        </h2>
                        <Link
                            href={route('seller.products.index')}
                            className="text-sm text-blue-600 hover:text-blue-700"
                        >
                            Voir tout →
                        </Link>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                        {topProducts.length > 0 ? (
                            topProducts.map((product) => (
                                <Link
                                    key={product.id}
                                    href={route(
                                        'seller.products.show',
                                        product.id,
                                    )}
                                    className="rounded-lg border border-border p-4 transition-colors hover:bg-muted"
                                >
                                    <div className="mb-3 aspect-square overflow-hidden rounded-md bg-muted">
                                        {product.images[0] && (
                                            <img
                                                src={product.images[0].url}
                                                alt={product.name}
                                                className="h-full w-full object-cover"
                                            />
                                        )}
                                    </div>
                                    <p className="line-clamp-2 text-sm font-medium text-foreground">
                                        {product.name}
                                    </p>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {product.order_items_count}{' '}
                                        {product.order_items_count > 1
                                            ? 'ventes'
                                            : 'vente'}
                                    </p>
                                </Link>
                            ))
                        ) : (
                            <div className="col-span-full py-8 text-center text-muted-foreground">
                                Aucun produit pour le moment
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => (
    <SellerLayout>{page}</SellerLayout>
);
