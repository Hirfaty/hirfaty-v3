import { Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    Calendar,
    CheckCircle2,
    Clock,
    ExternalLink,
    KeyRound,
    Mail,
    MapPin,
    Package,
    Phone,
    ShoppingBag,
    Store,
    UserCheck,
    Wallet,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type { Order, PageProps, Product, StoreType, User } from '@/types';

/** Labels for the seller-type badge shown on the store card. */
const STORE_TYPE_LABEL: Record<StoreType, string> = {
    artist: 'Artiste',
    company: 'Entreprise',
    cooperative: 'Coopérative',
};

export type EarningsSummary = {
    total: number;
    pending: number;
    available: number;
    paid_out: number;
};

export type OrderStats = {
    total: number;
    pending: number;
    delivered: number;
    cancelled: number;
};

export type ProductStats = {
    total: number;
    published: number;
    pending_review: number;
    rejected: number;
};

interface Props {
    user: User & {
        store: {
            id: number;
            store_name: string;
            slug: string;
            description: string;
            type: StoreType;
            logo: string | null;
            banner: string | null;
            city: { name: string };
            // Category names are stored per locale; the resolved display string
            // is appended server-side as translated_name.
            category: { translated_name: string };
            products: Product[];
        };
    };
    earnings: EarningsSummary;
    orderStats: OrderStats;
    productStats: ProductStats;
    recentOrders: Order[];
}

const STATUS_BADGE: Record<string, string> = {
    pending_review: 'bg-amber-100 text-amber-700',
    published: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-rose-100 text-rose-700',
    archived: 'bg-slate-100 text-slate-700',
    draft: 'bg-slate-100 text-slate-700',
    pending: 'bg-amber-100 text-amber-700',
    confirmed: 'bg-blue-100 text-blue-700',
    shipped: 'bg-violet-100 text-violet-700',
    delivered: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-rose-100 text-rose-700',
};

const formatMAD = (value: number) =>
    new Intl.NumberFormat('fr-MA', {
        style: 'currency',
        currency: 'MAD',
    }).format(value);

/** Small bordered card, the base unit this whole page is built from. */
function Card({
    title,
    icon: Icon,
    action,
    children,
    className = '',
}: {
    title?: string;
    icon?: typeof Package;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={`overflow-hidden rounded-xl border border-border bg-card shadow-sm ${className}`}
        >
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

/** One tile in the top KPI row. */
function Stat({
    label,
    value,
    icon: Icon,
    tone = 'default',
}: {
    label: string;
    value: string | number;
    icon: typeof Package;
    tone?: 'default' | 'positive' | 'warning';
}) {
    const toneClass = {
        default: 'text-ink',
        positive: 'text-emerald-700',
        warning: 'text-amber-700',
    }[tone];

    return (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="flex items-center gap-2 text-xs font-medium text-ink-soft">
                <Icon className="h-3.5 w-3.5" />
                {label}
            </div>
            <p className={`mt-2 text-xl font-semibold ${toneClass}`}>{value}</p>
        </div>
    );
}

const ShowSeller = ({
    user,
    earnings,
    orderStats,
    productStats,
    recentOrders,
}: Props) => {
    const [sendingReset, setSendingReset] = useState(false);

    const handleBan = () => {
        if (
            confirm(
                `Voulez-vous ${user.is_banned ? 'débloquer' : 'bloquer'} cet utilisateur ?`,
            )
        ) {
            router.patch(route('admin.users.ban', user.id));
        }
    };

    const handleSendResetLink = () => {
        setSendingReset(true);
        router.post(route('admin.users.send-reset-link', user.id), undefined, {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props as unknown as PageProps).flash;

                if (flash?.error) {
                    toast.error(flash.error);
                } else {
                    toast.success(
                        flash?.success ?? 'Lien de réinitialisation envoyé.',
                    );
                }
            },
            onFinish: () => setSendingReset(false),
        });
    };

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            {/* ── Header ───────────────────────────────────── */}
            <div>
                <Link
                    href={route('admin.users.index')}
                    className="mb-3 inline-flex items-center gap-2 text-sm text-ink-soft transition hover:text-ink"
                >
                    <ArrowLeft className="h-4 w-4" /> Retour aux utilisateurs
                </Link>

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="text-2xl font-semibold tracking-tight text-ink">
                                {user.first_name} {user.last_name}
                            </h1>
                            <span className="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                Vendeur
                            </span>
                            {user.is_banned ? (
                                <Badge
                                    variant="destructive"
                                    className="rounded-full"
                                >
                                    Bloqué
                                </Badge>
                            ) : (
                                <></>
                            )}
                        </div>
                        <p className="mt-1 flex items-center gap-1.5 text-sm text-ink-soft">
                            <Calendar className="h-3.5 w-3.5" />
                            Membre depuis le {formatDate(user.created_at)}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        <Link
                            href={route('admin.products.create', {
                                store_id: user.store.id,
                            })}
                            className="inline-flex items-center gap-2 rounded-lg bg-ink px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90"
                        >
                            <Package className="h-4 w-4" />
                            Ajouter un produit
                        </Link>
                        <Button
                            variant="outline"
                            className="rounded-lg"
                            disabled={sendingReset}
                            onClick={handleSendResetLink}
                        >
                            <KeyRound className="h-4 w-4" />
                            Envoyer un lien de réinitialisation
                        </Button>
                        <Button
                            variant={user.is_banned ? 'outline' : 'destructive'}
                            className="rounded-lg"
                            onClick={handleBan}
                        >
                            {user.is_banned ? (
                                <>
                                    <UserCheck className="h-4 w-4" /> Débloquer
                                </>
                            ) : (
                                <>
                                    <Ban className="h-4 w-4" /> Bloquer
                                </>
                            )}
                        </Button>
                    </div>
                </div>
            </div>

            {/* ── KPI row ──────────────────────────────────── */}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <Stat
                    label="Gains nets"
                    value={formatMAD(earnings.total)}
                    icon={Wallet}
                />
                <Stat
                    label="Disponible"
                    value={formatMAD(earnings.available)}
                    icon={CheckCircle2}
                    tone="positive"
                />
                <Stat
                    label="En attente"
                    value={formatMAD(earnings.pending)}
                    icon={Clock}
                    tone="warning"
                />
                <Stat
                    label="Produits en ligne"
                    value={productStats.published}
                    icon={Package}
                />
                <Stat
                    label="Commandes livrées"
                    value={`${orderStats.delivered}/${orderStats.total}`}
                    icon={ShoppingBag}
                />
            </div>

            {/* ── Body ─────────────────────────────────────── */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                {/* Sidebar */}
                <aside className="space-y-6 lg:col-span-4">
                    <Card title="Contact">
                        <div className="space-y-4 p-5 text-sm">
                            <div className="flex items-start gap-3">
                                <Mail className="mt-0.5 h-4 w-4 shrink-0 text-ink-soft" />
                                <div className="min-w-0">
                                    <p className="text-xs text-ink-soft">
                                        Email
                                    </p>
                                    <p className="truncate text-ink">
                                        {user.email}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <Phone className="mt-0.5 h-4 w-4 shrink-0 text-ink-soft" />
                                <div>
                                    <p className="text-xs text-ink-soft">
                                        Téléphone
                                    </p>
                                    <p className="text-ink">
                                        {user.phone_number || 'N/A'}
                                    </p>
                                </div>
                            </div>
                            {user.store?.city && (
                                <div className="flex items-start gap-3">
                                    <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-ink-soft" />
                                    <div>
                                        <p className="text-xs text-ink-soft">
                                            Ville
                                        </p>
                                        <p className="text-ink">
                                            {user.store.city.name}
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </Card>

                    <Card
                        title="Boutique"
                        icon={Store}
                        action={
                            <Link
                                href={`/seller/${user.store?.slug}`}
                                className="inline-flex items-center gap-1 text-xs font-medium text-ink-soft transition hover:text-ink"
                            >
                                Voir en ligne
                                <ExternalLink className="h-3 w-3" />
                            </Link>
                        }
                    >
                        {/* Cover strip — omitted entirely when no banner was
                            uploaded, rather than showing an empty placeholder. */}
                        {user.store?.banner && (
                            <img
                                src={user.store.banner}
                                alt=""
                                className="h-24 w-full object-cover"
                            />
                        )}

                        <div className="space-y-4 p-5">
                            <div className="flex items-center gap-3">
                                {user.store?.logo ? (
                                    <img
                                        src={user.store.logo}
                                        alt=""
                                        className="h-10 w-10 shrink-0 rounded-full border border-border object-cover"
                                    />
                                ) : (
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted">
                                        <Store className="h-5 w-5 text-ink-soft" />
                                    </div>
                                )}
                                <p className="min-w-0 truncate text-sm font-semibold text-ink">
                                    {user.store?.store_name}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-1.5">
                                <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-ink-soft">
                                    {user.store?.category?.translated_name ||
                                        'Artisanat'}
                                </span>
                                <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-ink-soft">
                                    {user.store?.type
                                        ? STORE_TYPE_LABEL[user.store.type]
                                        : '—'}
                                </span>
                            </div>

                            <p className="text-sm leading-relaxed text-ink-soft">
                                {user.store?.description ||
                                    'Aucune description fournie.'}
                            </p>
                        </div>
                    </Card>
                </aside>

                {/* Main column */}
                <div className="space-y-6 lg:col-span-8">
                    <Card title="Commandes récentes" icon={ShoppingBag}>
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>ID</TableHead>
                                    <TableHead>Client</TableHead>
                                    <TableHead>Montant</TableHead>
                                    <TableHead>Statut</TableHead>
                                    <TableHead className="text-right">
                                        Date
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recentOrders.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="h-24 text-center text-ink-soft"
                                        >
                                            Aucune commande enregistrée.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    recentOrders.map((order) => (
                                        <TableRow key={order.id}>
                                            <TableCell className="font-mono text-xs text-ink-soft">
                                                {order.reference}
                                            </TableCell>
                                            <TableCell className="text-sm text-ink">
                                                {order.buyer?.first_name}{' '}
                                                {order.buyer?.last_name}
                                            </TableCell>
                                            <TableCell className="text-sm font-medium text-ink">
                                                {formatMAD(
                                                    Number(order.total_amount),
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_BADGE[order.status] ?? 'bg-muted text-ink-soft'}`}
                                                >
                                                    {order.status}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right text-xs text-ink-soft">
                                                {formatDate(order.created_at)}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </Card>

                    <Card title="Produits" icon={Package}>
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Produit</TableHead>
                                    <TableHead>Prix</TableHead>
                                    <TableHead>Stock</TableHead>
                                    <TableHead>Statut</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {(user.store?.products.length ?? 0) === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="h-24 text-center text-ink-soft"
                                        >
                                            Aucun produit en ligne.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    user.store?.products.map((product) => (
                                        <TableRow key={product.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <div className="h-9 w-9 shrink-0 overflow-hidden rounded-lg border border-border bg-muted">
                                                        {product
                                                            .images?.[0] && (
                                                            <img
                                                                src={
                                                                    product
                                                                        .images[0]
                                                                        .url
                                                                }
                                                                alt=""
                                                                className="h-full w-full object-cover"
                                                            />
                                                        )}
                                                    </div>
                                                    <span className="text-sm font-medium text-ink">
                                                        {product.name}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm text-ink">
                                                {formatMAD(
                                                    Number(
                                                        product.sale_price ||
                                                            product.base_price,
                                                    ),
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm text-ink-soft">
                                                {product.has_variants
                                                    ? 'Géré par déclinaison'
                                                    : `${product.stock} unités`}
                                            </TableCell>
                                            <TableCell>
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_BADGE[product.status] ?? 'bg-muted text-ink-soft'}`}
                                                >
                                                    {product.status}
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </Card>
                </div>
            </div>
        </div>
    );
};

ShowSeller.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default ShowSeller;
