import AdminLayout from '@/layouts/admin/layout';
import type { PageProps, Product, User } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowUpRight,
    CheckCircle,
    LayoutGrid,
    Package,
    Settings,
    ShoppingBag,
    Users,
} from 'lucide-react';
import { ReactNode } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface DashboardStats {
    total_orders: number;
    total_revenue: number;
    pending_products: number;
    total_users: number;
    total_sellers: number;
    total_buyers: number;
    total_platform_fees: number;
    pending_payouts: number;
    total_paid_out: number;
    seller_balance_pending: number;
    seller_balance_available: number;
}

interface SalesTrend {
    total: number;
    commission: number;
    count: number;
    month: string;
}

const AdminDashboard = ({
    stats,
    latestUsers,
    pendingProducts,
    salesTrend,
}: PageProps & {
    stats: DashboardStats;
    latestUsers: User[];
    pendingProducts: Product[];
    salesTrend: SalesTrend[];
}) => {
    const formatCurrency = (amount: number) => 
        new Intl.NumberFormat('fr-MA', {
            style: 'currency',
            currency: 'MAD',
        }).format(amount);

    const kpiCards = [
        {
            label: 'Global Revenue',
            value: formatCurrency(stats.total_revenue),
            icon: ShoppingBag,
            trend: '+12.5%',
            color: 'text-terracotta',
        },
        {
            label: 'Platform Net (Fees)',
            value: formatCurrency(stats.total_platform_fees),
            icon: Activity,
            trend: 'Profit Margin',
            color: 'text-ink',
        },
        {
            label: 'Pending Payouts',
            value: formatCurrency(stats.pending_payouts),
            icon: Package,
            trend: 'Action Required',
            color: 'text-terracotta',
        },
        {
            label: 'Total Paid Out',
            value: formatCurrency(stats.total_paid_out),
            icon: CheckCircle,
            trend: 'Liquidity flow',
            color: 'text-ink',
        },
    ];

    return (
        <div className="min-h-full p-6 md:p-10">
            <Head title="Admin Dashboard" />

            <div className="mx-auto max-w-7xl">
                <header className="mb-10 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="font-sans text-xs font-medium uppercase tracking-[0.2em] text-ink-soft">
                            Financial Command
                        </p>
                        <h1 className="h-editorial mt-2 text-4xl text-ink md:text-5xl">
                            Command <span className="italic">Center</span>
                        </h1>
                    </div>
                    <div className="flex gap-3">
                        <Link
                            href="/admin/payouts"
                            className="bg-ink px-6 py-3 text-xs font-bold uppercase tracking-widest text-cream hover:bg-terracotta transition-colors"
                        >
                            Manage Payouts
                        </Link>
                    </div>
                </header>

                {/* ── KPI Grid ── */}
                <div className="mb-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {kpiCards.map((card, i) => (
                        <div
                            key={i}
                            className="border border-ink/5 bg-white p-8 shadow-sm transition-transform hover:-translate-y-1"
                        >
                            <div className="mb-4 flex items-center justify-between">
                                <card.icon className={`h-5 w-5 ${card.color}`} />
                                <span className="text-[10px] font-bold uppercase tracking-widest text-ink/30">
                                    {card.trend}
                                </span>
                            </div>
                            <p className="font-sans text-[10px] font-bold uppercase tracking-widest text-ink-soft">
                                {card.label}
                            </p>
                            <p className="h-editorial mt-1 text-3xl text-ink">
                                {card.value}
                            </p>
                        </div>
                    ))}
                </div>

                {/* ── Secondary Stats: Seller Balances ── */}
                <div className="mb-10 grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div className="flex items-center gap-6 border border-ink/5 bg-white p-6 shadow-sm">
                        <div className="flex h-12 w-12 items-center justify-center bg-cream/50 text-ink-soft">
                            <Users className="h-6 w-6" />
                        </div>
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-ink-soft">Artisan Base</p>
                            <p className="font-sans text-lg font-bold text-ink">{stats.total_sellers} Shops</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-6 border border-ink/5 bg-white p-6 shadow-sm">
                        <div className="flex h-12 w-12 items-center justify-center bg-cream/50 text-terracotta">
                            <Activity className="h-6 w-6" />
                        </div>
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-ink-soft">In-Transit Balance</p>
                            <p className="font-sans text-lg font-bold text-ink">{formatCurrency(stats.seller_balance_pending)}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-6 border border-ink/5 bg-white p-6 shadow-sm">
                        <div className="flex h-12 w-12 items-center justify-center bg-cream/50 text-ink">
                            <CheckCircle className="h-6 w-6" />
                        </div>
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-ink-soft">Claimable Earnings</p>
                            <p className="font-sans text-lg font-bold text-ink">{formatCurrency(stats.seller_balance_available)}</p>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-12 gap-10">
                    {/* ── Performance Chart ── */}
                    <div className="col-span-12 lg:col-span-8">
                        <div className="border border-ink/5 bg-white p-8 shadow-sm">
                            <div className="mb-8 flex items-center justify-between">
                                <h3 className="font-sans text-xs font-bold uppercase tracking-widest text-ink">
                                    Revenue vs Commission
                                </h3>
                                <div className="flex gap-6">
                                    <div className="flex items-center gap-2">
                                        <div className="h-2 w-2 rounded-full bg-terracotta" />
                                        <span className="text-[10px] font-bold uppercase tracking-widest text-ink-soft">
                                            Gross
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="h-2 w-2 rounded-full bg-ink" />
                                        <span className="text-[10px] font-bold uppercase tracking-widest text-ink-soft">
                                            Platform
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div className="h-[350px] w-full">
                                <ResponsiveContainer width="100%" height="100%">
                                    <AreaChart data={salesTrend}>
                                        <defs>
                                            <linearGradient id="colorTotal" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="#A84C2E" stopOpacity={0.1}/>
                                                <stop offset="95%" stopColor="#A84C2E" stopOpacity={0}/>
                                            </linearGradient>
                                            <linearGradient id="colorComm" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="#121212" stopOpacity={0.1}/>
                                                <stop offset="95%" stopColor="#121212" stopOpacity={0}/>
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid vertical={false} strokeDasharray="3 3" stroke="#f1f1f1" />
                                        <XAxis dataKey="month" axisLine={false} tickLine={false} style={{ fontSize: '10px', fontFamily: 'Inter', fill: '#999' }} dy={10} />
                                        <YAxis axisLine={false} tickLine={false} style={{ fontSize: '10px', fontFamily: 'Inter', fill: '#999' }} />
                                        <Tooltip
                                            contentStyle={{ backgroundColor: '#121212', border: 'none', borderRadius: '0px', color: '#F9F7F2', fontSize: '12px' }}
                                            itemStyle={{ color: '#F9F7F2' }}
                                        />
                                        <Area type="monotone" dataKey="total" stroke="#A84C2E" strokeWidth={2} fillOpacity={1} fill="url(#colorTotal)" />
                                        <Area type="monotone" dataKey="commission" stroke="#121212" strokeWidth={2} fillOpacity={1} fill="url(#colorComm)" />
                                    </AreaChart>
                                </ResponsiveContainer>
                            </div>
                        </div>
                    </div>

                    {/* ── Quick Tools ── */}
                    <div className="col-span-12 lg:col-span-4">
                        <div className="border border-ink/5 bg-white p-8 shadow-sm">
                            <h3 className="font-sans text-xs font-bold uppercase tracking-widest text-ink mb-6">
                                Administrative Tools
                            </h3>
                            <div className="grid grid-cols-2 gap-4">
                                <Link
                                    href="/admin/categories"
                                    className="flex flex-col items-center justify-center gap-3 border border-ink/5 bg-cream/10 p-6 text-center transition-colors hover:border-terracotta hover:bg-cream/20"
                                >
                                    <LayoutGrid className="h-6 w-6 text-ink/40" />
                                    <span className="text-[10px] font-bold uppercase tracking-widest text-ink-soft">
                                        Taxonomy
                                    </span>
                                </Link>
                                <Link
                                    href="/admin/cities"
                                    className="flex flex-col items-center justify-center gap-3 border border-ink/5 bg-cream/10 p-6 text-center transition-colors hover:border-terracotta hover:bg-cream/20"
                                >
                                    <ShoppingBag className="h-6 w-6 text-ink/40" />
                                    <span className="text-[10px] font-bold uppercase tracking-widest text-ink-soft">
                                        Logistics
                                    </span>
                                </Link>
                                <Link
                                    href="/admin/users"
                                    className="flex flex-col items-center justify-center gap-3 border border-ink/5 bg-cream/10 p-6 text-center transition-colors hover:border-terracotta hover:bg-cream/20"
                                >
                                    <Users className="h-6 w-6 text-ink/40" />
                                    <span className="text-[10px] font-bold uppercase tracking-widest text-ink-soft">
                                        Audience
                                    </span>
                                </Link>
                                <Link
                                    href="/settings"
                                    className="flex flex-col items-center justify-center gap-3 border border-ink/5 bg-cream/10 p-6 text-center transition-colors hover:border-terracotta hover:bg-cream/20"
                                >
                                    <Settings className="h-6 w-6 text-ink/40" />
                                    <span className="text-[10px] font-bold uppercase tracking-widest text-ink-soft">
                                        Config
                                    </span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="mt-10 grid grid-cols-12 gap-10">
                    {/* ── Pending Approvals ── */}
                    <div className="col-span-12 lg:col-span-6">
                        <div className="border border-ink/5 bg-white shadow-sm">
                            <div className="flex items-center justify-between border-b border-ink/5 p-6">
                                <h3 className="font-sans text-xs font-bold uppercase tracking-widest text-ink">
                                    Awaiting Approval
                                </h3>
                                <Link
                                    href="/admin/products"
                                    className="text-[10px] font-bold uppercase tracking-widest text-terracotta hover:text-ink"
                                >
                                    View All
                                </Link>
                            </div>
                            <div className="divide-y divide-ink/5">
                                {pendingProducts.length > 0 ? (
                                    pendingProducts.map((product) => (
                                        <div
                                            key={product.id}
                                            className="flex items-center justify-between p-6 hover:bg-cream/5"
                                        >
                                            <div className="flex items-center gap-4">
                                                <div className="h-10 w-10 bg-cream/50 overflow-hidden border border-ink/5">
                                                    {product.media?.[0] && (
                                                        <img
                                                            src={product.media[0].url}
                                                            className="h-full w-full object-cover"
                                                        />
                                                    )}
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium text-ink">
                                                        {product.name}
                                                    </p>
                                                    <p className="text-[10px] text-ink-soft uppercase tracking-wider">
                                                        By {product.store?.store_name}
                                                    </p>
                                                </div>
                                            </div>
                                            <Link
                                                href={`/admin/products/${product.id}`}
                                                className="p-2 text-ink-soft hover:text-ink"
                                            >
                                                <ArrowUpRight className="h-4 w-4" />
                                            </Link>
                                        </div>
                                    ))
                                ) : (
                                    <div className="p-10 text-center text-[10px] font-bold uppercase tracking-widest text-ink-soft">
                                        All inventory is up to date
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* ── Latest Users ── */}
                    <div className="col-span-12 lg:col-span-6">
                        <div className="border border-ink/5 bg-white shadow-sm">
                            <div className="flex items-center justify-between border-b border-ink/5 p-6">
                                <h3 className="font-sans text-xs font-bold uppercase tracking-widest text-ink">
                                    Latest Registrations
                                </h3>
                                <Link
                                    href="/admin/users"
                                    className="text-[10px] font-bold uppercase tracking-widest text-terracotta hover:text-ink"
                                >
                                    Directory
                                </Link>
                            </div>
                            <div className="divide-y divide-ink/5">
                                {latestUsers.map((user) => (
                                    <div
                                        key={user.id}
                                        className="flex items-center justify-between p-6 hover:bg-cream/5"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-10 w-10 items-center justify-center bg-cream text-[10px] font-bold text-ink-soft uppercase border border-ink/5">
                                                {user.first_name[0]}
                                                {user.last_name[0]}
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium text-ink">
                                                    {user.first_name} {user.last_name}
                                                </p>
                                                <p className="text-[10px] text-ink-soft uppercase tracking-wider">
                                                    {user.role} — Joined{' '}
                                                    {new Date(
                                                        user.created_at as string,
                                                    ).toLocaleDateString()}
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            className={`rounded-full px-2 py-0.5 text-[8px] font-bold uppercase tracking-widest ${
                                                user.role === 'seller'
                                                    ? 'bg-terracotta/10 text-terracotta'
                                                    : 'bg-ink/5 text-ink-soft'
                                            }`}
                                        >
                                            {user.role}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

AdminDashboard.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default AdminDashboard;
