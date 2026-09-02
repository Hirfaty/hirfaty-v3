import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    CheckCircle2,
    Clock,
    LayoutGrid,
    MapPin,
    Package,
    Settings,
    ShoppingBag,
    Users,
    Wallet,
} from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import HealthPanel from '@/components/admin/health-panel';
import type { HealthReport } from '@/components/admin/health-panel';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type { PageProps, Product, User, UserRole } from '@/types';

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

const ROLE_LABEL: Record<UserRole, string> = {
    buyer: 'Acheteur',
    professional_buyer: 'Acheteur pro',
    seller: 'Vendeur',
    super_admin: 'Administrateur',
};

const ROLE_BADGE: Record<UserRole, string> = {
    buyer: 'bg-slate-100 text-slate-700',
    professional_buyer: 'bg-violet-100 text-violet-700',
    seller: 'bg-emerald-100 text-emerald-700',
    super_admin: 'bg-amber-100 text-amber-700',
};

// SVG color strings, not Tailwind classes — Recharts writes these straight
// onto <stop>/<Area> attributes, so they must match the design tokens'
// actual oklch() values (resources/css/app.css) rather than a class name.
const CHART_TERRACOTTA = 'oklch(63% 0.17 41)';
const CHART_INK = 'oklch(21% 0.02 255)';

/** Small bordered card, the base unit this whole page is built from. */
function Card({
    title,
    icon: Icon,
    action,
    children,
}: {
    title?: string;
    icon?: typeof Package;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            {title && (
                <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-3.5">
                    <div className="flex items-center gap-2">
                        {Icon && <Icon className="h-4 w-4 text-ink-soft" />}
                        <h2 className="text-sm font-semibold text-ink">
                            {title}
                        </h2>
                    </div>
                    {action}
                </div>
            )}
            {children}
        </div>
    );
}

function Stat({
    label,
    value,
    icon: Icon,
    tone = 'default',
    href,
}: {
    label: string;
    value: string | number;
    icon: typeof Package;
    tone?: 'default' | 'positive' | 'warning';
    href?: string;
}) {
    const toneClass = {
        default: 'text-ink',
        positive: 'text-emerald-700',
        warning: 'text-amber-700',
    }[tone];

    const content = (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm transition hover:border-ink/20">
            <div className="flex items-center gap-2 text-xs font-medium text-ink-soft">
                <Icon className="h-3.5 w-3.5" />
                {label}
            </div>
            <p className={`mt-2 text-xl font-semibold ${toneClass}`}>{value}</p>
        </div>
    );

    return href ? <Link href={href}>{content}</Link> : content;
}

const AdminDashboard = ({
    stats,
    latestUsers,
    pendingProducts,
    salesTrend,
    health,
}: PageProps & {
    stats: DashboardStats;
    latestUsers: User[];
    pendingProducts: Product[];
    salesTrend: SalesTrend[];
    health: HealthReport;
}) => {
    const formatCurrency = (amount: number) =>
        new Intl.NumberFormat('fr-MA', {
            style: 'currency',
            currency: 'MAD',
        }).format(amount);

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title="Tableau de bord" />

            {/* ── Header ───────────────────────────────────── */}
            <header>
                <h1 className="text-2xl font-semibold tracking-tight text-ink">
                    Tableau de bord
                </h1>
                <p className="mt-1 text-sm text-ink-soft">
                    Vue d'ensemble de l'activité de la marketplace.
                </p>
            </header>

            {/* ── Financials ───────────────────────────────── */}
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Stat
                    label="Chiffre d'affaires"
                    value={formatCurrency(stats.total_revenue)}
                    icon={ShoppingBag}
                />
                <Stat
                    label="Frais de plateforme"
                    value={formatCurrency(stats.total_platform_fees)}
                    icon={Wallet}
                />
                <Stat
                    label="Paiements en attente"
                    value={formatCurrency(stats.pending_payouts)}
                    icon={Clock}
                    tone="warning"
                />
                <Stat
                    label="Total versé"
                    value={formatCurrency(stats.total_paid_out)}
                    icon={CheckCircle2}
                    tone="positive"
                />
            </div>

            {/* ── Activity ─────────────────────────────────── */}
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Stat
                    label="Commandes"
                    value={stats.total_orders}
                    icon={Package}
                />
                <Stat
                    label="Vendeurs"
                    value={stats.total_sellers}
                    icon={Users}
                    href={route('admin.users.index', { role: 'seller' })}
                />
                <Stat
                    label="Acheteurs"
                    value={stats.total_buyers}
                    icon={Users}
                    href={route('admin.users.index', { role: 'buyer' })}
                />
                <Stat
                    label="Produits en attente"
                    value={stats.pending_products}
                    icon={Clock}
                    tone={stats.pending_products > 0 ? 'warning' : 'default'}
                    href={route('admin.products.index', {
                        status: 'pending_review',
                    })}
                />
            </div>

            {/* ── Seller balances ──────────────────────────── */}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <Stat
                    label="Solde vendeurs en transit"
                    value={formatCurrency(stats.seller_balance_pending)}
                    icon={Clock}
                    tone="warning"
                />
                <Stat
                    label="Gains vendeurs réclamables"
                    value={formatCurrency(stats.seller_balance_available)}
                    icon={CheckCircle2}
                    tone="positive"
                />
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                {/* ── Sales trend chart ──────────────────────── */}
                <div className="lg:col-span-8">
                    <Card title="Évolution des ventes">
                        <div className="flex items-center gap-4 border-b border-border px-5 py-3">
                            <div className="flex items-center gap-1.5">
                                <div className="h-2 w-2 rounded-full bg-terracotta" />
                                <span className="text-xs font-medium text-ink-soft">
                                    Brut
                                </span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <div className="h-2 w-2 rounded-full bg-ink" />
                                <span className="text-xs font-medium text-ink-soft">
                                    Commission plateforme
                                </span>
                            </div>
                        </div>
                        <div className="h-[300px] w-full p-5">
                            <ResponsiveContainer width="100%" height="100%">
                                <AreaChart data={salesTrend}>
                                    <defs>
                                        <linearGradient
                                            id="colorTotal"
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
                                            <stop
                                                offset="5%"
                                                stopColor={CHART_TERRACOTTA}
                                                stopOpacity={0.15}
                                            />
                                            <stop
                                                offset="95%"
                                                stopColor={CHART_TERRACOTTA}
                                                stopOpacity={0}
                                            />
                                        </linearGradient>
                                        <linearGradient
                                            id="colorComm"
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
                                            <stop
                                                offset="5%"
                                                stopColor={CHART_INK}
                                                stopOpacity={0.15}
                                            />
                                            <stop
                                                offset="95%"
                                                stopColor={CHART_INK}
                                                stopOpacity={0}
                                            />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid
                                        vertical={false}
                                        strokeDasharray="3 3"
                                        stroke="oklch(91% 0.004 255)"
                                    />
                                    <XAxis
                                        dataKey="month"
                                        axisLine={false}
                                        tickLine={false}
                                        style={{
                                            fontSize: '11px',
                                            fontFamily: 'var(--font-sans)',
                                            fill: 'oklch(52% 0.015 255)',
                                        }}
                                        dy={10}
                                    />
                                    <YAxis
                                        axisLine={false}
                                        tickLine={false}
                                        style={{
                                            fontSize: '11px',
                                            fontFamily: 'var(--font-sans)',
                                            fill: 'oklch(52% 0.015 255)',
                                        }}
                                    />
                                    <Tooltip
                                        formatter={(value) =>
                                            formatCurrency(Number(value))
                                        }
                                        contentStyle={{
                                            backgroundColor: CHART_INK,
                                            border: 'none',
                                            borderRadius: '8px',
                                            fontSize: '12px',
                                        }}
                                        itemStyle={{ color: '#fff' }}
                                        labelStyle={{ color: '#fff' }}
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="total"
                                        name="Brut"
                                        stroke={CHART_TERRACOTTA}
                                        strokeWidth={2}
                                        fillOpacity={1}
                                        fill="url(#colorTotal)"
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="commission"
                                        name="Commission"
                                        stroke={CHART_INK}
                                        strokeWidth={2}
                                        fillOpacity={1}
                                        fill="url(#colorComm)"
                                    />
                                </AreaChart>
                            </ResponsiveContainer>
                        </div>
                    </Card>
                </div>

                {/* ── Quick tools ────────────────────────────── */}
                <div className="lg:col-span-4">
                    <Card title="Outils">
                        <div className="grid grid-cols-2 gap-3 p-5">
                            <Link
                                href={route('admin.categories.index')}
                                className="flex flex-col items-center justify-center gap-2 rounded-lg border border-border bg-muted/30 p-5 text-center transition hover:border-ink/30 hover:bg-muted/50"
                            >
                                <LayoutGrid className="h-5 w-5 text-ink-soft" />
                                <span className="text-xs font-medium text-ink">
                                    Catégories
                                </span>
                            </Link>
                            <Link
                                href={route('admin.cities.index')}
                                className="flex flex-col items-center justify-center gap-2 rounded-lg border border-border bg-muted/30 p-5 text-center transition hover:border-ink/30 hover:bg-muted/50"
                            >
                                <MapPin className="h-5 w-5 text-ink-soft" />
                                <span className="text-xs font-medium text-ink">
                                    Villes
                                </span>
                            </Link>
                            <Link
                                href={route('admin.users.index')}
                                className="flex flex-col items-center justify-center gap-2 rounded-lg border border-border bg-muted/30 p-5 text-center transition hover:border-ink/30 hover:bg-muted/50"
                            >
                                <Users className="h-5 w-5 text-ink-soft" />
                                <span className="text-xs font-medium text-ink">
                                    Utilisateurs
                                </span>
                            </Link>
                            <Link
                                href="/settings"
                                className="flex flex-col items-center justify-center gap-2 rounded-lg border border-border bg-muted/30 p-5 text-center transition hover:border-ink/30 hover:bg-muted/50"
                            >
                                <Settings className="h-5 w-5 text-ink-soft" />
                                <span className="text-xs font-medium text-ink">
                                    Paramètres
                                </span>
                            </Link>
                        </div>
                    </Card>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* ── Pending approvals ──────────────────────── */}
                <Card
                    title="En attente d'approbation"
                    icon={Clock}
                    action={
                        <Link
                            href={route('admin.products.index', {
                                status: 'pending_review',
                            })}
                            className="text-xs font-medium text-ink-soft transition hover:text-ink"
                        >
                            Voir tout
                        </Link>
                    }
                >
                    <div className="divide-y divide-border">
                        {pendingProducts.length > 0 ? (
                            pendingProducts.map((product) => (
                                <Link
                                    key={product.id}
                                    href={route(
                                        'admin.products.show',
                                        product.id,
                                    )}
                                    className="flex items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-muted/40"
                                >
                                    <div className="flex min-w-0 items-center gap-3">
                                        <div className="h-9 w-9 shrink-0 overflow-hidden rounded-lg border border-border bg-muted">
                                            {product.media?.[0] && (
                                                <img
                                                    src={product.media[0].url}
                                                    alt=""
                                                    className="h-full w-full object-cover"
                                                />
                                            )}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-ink">
                                                {product.name}
                                            </p>
                                            <p className="truncate text-xs text-ink-soft">
                                                {product.store?.store_name}
                                            </p>
                                        </div>
                                    </div>
                                    <ArrowUpRight className="h-4 w-4 shrink-0 text-ink-soft" />
                                </Link>
                            ))
                        ) : (
                            <div className="p-8 text-center text-sm text-ink-soft">
                                Aucun produit en attente — tout est à jour.
                            </div>
                        )}
                    </div>
                </Card>

                {/* ── Latest registrations ───────────────────── */}
                <Card
                    title="Derniers inscrits"
                    icon={Users}
                    action={
                        <Link
                            href={route('admin.users.index')}
                            className="text-xs font-medium text-ink-soft transition hover:text-ink"
                        >
                            Annuaire
                        </Link>
                    }
                >
                    <div className="divide-y divide-border">
                        {latestUsers.length > 0 ? (
                            latestUsers.map((user) => (
                                <Link
                                    key={user.id}
                                    href={route('admin.users.show', user.id)}
                                    className="flex items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-muted/40"
                                >
                                    <div className="flex min-w-0 items-center gap-3">
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-ink-soft">
                                            {user.first_name[0]}
                                            {user.last_name[0]}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-ink">
                                                {user.first_name}{' '}
                                                {user.last_name}
                                            </p>
                                            <p className="truncate text-xs text-ink-soft">
                                                Inscrit le{' '}
                                                {formatDate(user.created_at)}
                                            </p>
                                        </div>
                                    </div>
                                    <span
                                        className={`inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-medium ${ROLE_BADGE[user.role]}`}
                                    >
                                        {ROLE_LABEL[user.role]}
                                    </span>
                                </Link>
                            ))
                        ) : (
                            <div className="p-8 text-center text-sm text-ink-soft">
                                Aucun utilisateur inscrit récemment.
                            </div>
                        )}
                    </div>
                </Card>
            </div>

            {/* ── System health ────────────────────────────── */}
            <HealthPanel report={health} />
        </div>
    );
};

AdminDashboard.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default AdminDashboard;
