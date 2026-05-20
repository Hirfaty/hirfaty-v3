import { Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    ExternalLink,
    MapPin,
    Package,
    ShoppingBag,
    Store,
    UserCheck,
    Wallet,
} from 'lucide-react';
import type { ReactNode } from 'react';
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
import type { Order, Product, User } from '@/types';

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
            city: { name: string };
            category: { name: string };
            products: Product[];
        };
    };
    earnings: EarningsSummary;
    orderStats: OrderStats;
    productStats: ProductStats;
    recentOrders: Order[];
}

const statusPill: Record<string, string> = {
    pending_review: 'bg-amber-100 text-amber-800',
    published: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-800',
    archived: 'bg-slate-100 text-slate-800',
    pending: 'bg-amber-100 text-amber-800',
    confirmed: 'bg-blue-100 text-blue-800',
    shipped: 'bg-purple-100 text-purple-800',
    delivered: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-rose-100 text-rose-800',
};

const formatMAD = (value: number) =>
    new Intl.NumberFormat('fr-MA', {
        style: 'currency',
        currency: 'MAD',
    }).format(value);

const ShowSeller = ({
    user,
    earnings,
    orderStats,
    productStats,
    recentOrders,
}: Props) => {
    const handleBan = () => {
        if (
            confirm(
                `Voulez-vous ${user.is_banned ? 'débloquer' : 'bloquer'} cet utilisateur ?`,
            )
        ) {
            router.patch(route('admin.users.ban', user.id));
        }
    };

    return (
        <div className="flex flex-col gap-8 p-6 md:p-8">
            <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <Link
                        href={route('admin.users.index')}
                        className="mb-2 flex items-center gap-2 text-sm text-ink-soft hover:text-ink"
                    >
                        <ArrowLeft className="h-4 w-4" /> Retour aux
                        utilisateurs
                    </Link>
                    <div className="flex items-center gap-4">
                        <h1 className="h-editorial text-4xl text-ink md:text-5xl">
                            {user.first_name} {user.last_name}
                        </h1>
                        <Badge variant="default" className="rounded-none">
                            Vendeur
                        </Badge>
                        {user.is_banned && (
                            <Badge
                                variant="destructive"
                                className="rounded-none"
                            >
                                Bloqué
                            </Badge>
                        )}
                    </div>
                    <p className="mt-2 text-ink-soft">
                        Membre depuis le {formatDate(user.created_at)}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Link
                        href={route('admin.products.create', {
                            store_id: user.store.id,
                        })}
                        className="inline-flex items-center gap-2 rounded-none border border-ink/10 bg-cream px-4 py-3 text-xs font-bold tracking-widest text-ink uppercase transition-colors hover:bg-ink hover:text-cream"
                    >
                        <Package className="h-4 w-4" />
                        Ajouter un produit
                    </Link>
                    <Button
                        variant={user.is_banned ? 'outline' : 'destructive'}
                        className="rounded-none"
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
            </header>

            <div className="grid grid-cols-1 gap-8 lg:grid-cols-12">
                {/* Seller Info */}
                <aside className="space-y-6 lg:col-span-4">
                    <div className="border border-ink/10 bg-paper p-6">
                        <p className="eyebrow mb-6 text-xs">
                            Informations Contact
                        </p>
                        <div className="space-y-4 text-sm">
                            <div>
                                <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                    Email
                                </p>
                                <p className="mt-1 text-ink">{user.email}</p>
                            </div>
                            <div>
                                <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                    Téléphone
                                </p>
                                <p className="mt-1 text-ink">
                                    {user.phone_number || 'N/A'}
                                </p>
                            </div>
                            {user.store?.city && (
                                <div>
                                    <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                        Ville
                                    </p>
                                    <p className="mt-1 flex items-center gap-1 text-ink">
                                        <MapPin className="h-3.5 w-3.5" />{' '}
                                        {user.store.city.name}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="border border-ink/10 bg-paper p-6">
                        <p className="eyebrow mb-6 text-xs">Résumé Financier</p>
                        <div className="space-y-6">
                            <div className="flex items-center gap-4">
                                <div className="flex h-10 w-10 items-center justify-center bg-cream">
                                    <Wallet className="h-5 w-5 text-ink" />
                                </div>
                                <div>
                                    <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                        Gains Totaux (Net)
                                    </p>
                                    <p className="font-display text-xl text-ink">
                                        {formatMAD(earnings.total)}
                                    </p>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4 border-t border-ink/5 pt-4">
                                <div>
                                    <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                        Disponible
                                    </p>
                                    <p className="font-display text-lg text-emerald-700">
                                        {formatMAD(earnings.available)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                        En attente
                                    </p>
                                    <p className="font-display text-lg text-amber-700">
                                        {formatMAD(earnings.pending)}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>

                {/* Store Details & Stats */}
                <div className="space-y-8 lg:col-span-8">
                    <div className="border border-ink/10 bg-paper p-8">
                        <div className="mb-6 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <Store className="h-6 w-6 text-terracotta" />
                                <h3 className="h-editorial text-2xl text-ink">
                                    Boutique : {user.store?.store_name}
                                </h3>
                            </div>
                            <Link
                                href={`/seller/${user.store?.slug}`}
                                className="flex items-center gap-1 text-sm text-ink-soft hover:text-ink hover:underline"
                            >
                                Voir en ligne{' '}
                                <ExternalLink className="h-3 w-3" />
                            </Link>
                        </div>

                        <div className="grid grid-cols-1 gap-8 md:grid-cols-2">
                            <div className="space-y-4">
                                <div>
                                    <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                        Catégorie
                                    </p>
                                    <Badge
                                        variant="secondary"
                                        className="mt-1 rounded-none"
                                    >
                                        {user.store?.category?.name ||
                                            'Artisanat'}
                                    </Badge>
                                </div>
                                <div>
                                    <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                        Description
                                    </p>
                                    <p className="mt-1 text-sm leading-relaxed text-ink-soft italic">
                                        "
                                        {user.store?.description ||
                                            'Aucune description fournie.'}
                                        "
                                    </p>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4 border border-ink/5 bg-cream/30 p-4">
                                <div className="text-center">
                                    <p className="font-display text-2xl text-ink">
                                        {productStats.published}
                                    </p>
                                    <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                        Produits en ligne
                                    </p>
                                </div>
                                <div className="text-center">
                                    <p className="font-display text-2xl text-ink">
                                        {orderStats.total}
                                    </p>
                                    <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                        Ventes totales
                                    </p>
                                </div>
                                <div className="col-span-2 mt-2 border-t border-ink/5 pt-2 text-center">
                                    <p className="font-display text-lg text-ink">
                                        {orderStats.delivered}
                                    </p>
                                    <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                        Commandes livrées
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Recent Orders */}
                    <div className="overflow-hidden border border-ink/10 bg-paper">
                        <div className="flex items-center gap-2 border-b border-ink/10 bg-cream/50 px-6 py-4">
                            <ShoppingBag className="h-4 w-4 text-ink" />
                            <h3 className="font-display text-lg text-ink">
                                Commandes Récentes
                            </h3>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="text-[10px] font-bold tracking-widest uppercase">
                                        ID
                                    </TableHead>
                                    <TableHead className="text-[10px] font-bold tracking-widest uppercase">
                                        Client
                                    </TableHead>
                                    <TableHead className="text-[10px] font-bold tracking-widest uppercase">
                                        Montant
                                    </TableHead>
                                    <TableHead className="text-[10px] font-bold tracking-widest uppercase">
                                        Statut
                                    </TableHead>
                                    <TableHead className="text-right text-[10px] font-bold tracking-widest uppercase">
                                        Date
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recentOrders.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="h-24 text-center text-ink-soft italic"
                                        >
                                            Aucune commande enregistrée.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    recentOrders.map((order) => (
                                        <TableRow key={order.id}>
                                            <TableCell className="font-mono text-xs">
                                                #{order.id}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {order.buyer?.first_name}{' '}
                                                {order.buyer?.last_name}
                                            </TableCell>
                                            <TableCell className="font-display text-ink">
                                                {formatMAD(
                                                    Number(order.total_amount),
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    className={`rounded-none border-none ${statusPill[order.status]}`}
                                                >
                                                    {order.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right text-xs text-ink-soft">
                                                {formatDate(order.created_at)}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    {/* Product Management */}
                    <div className="overflow-hidden border border-ink/10 bg-paper">
                        <div className="flex items-center gap-2 border-b border-ink/10 bg-cream/50 px-6 py-4">
                            <Package className="h-4 w-4 text-ink" />
                            <h3 className="font-display text-lg text-ink">
                                Gestion des Produits
                            </h3>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="text-[10px] font-bold tracking-widest uppercase">
                                        Produit
                                    </TableHead>
                                    <TableHead className="text-[10px] font-bold tracking-widest uppercase">
                                        Prix
                                    </TableHead>
                                    <TableHead className="text-[10px] font-bold tracking-widest uppercase">
                                        Stock
                                    </TableHead>
                                    <TableHead className="text-[10px] font-bold tracking-widest uppercase">
                                        Statut
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {user.store?.products.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="h-24 text-center text-ink-soft italic"
                                        >
                                            Aucun produit en ligne.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    user.store?.products.map((product) => (
                                        <TableRow key={product.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <div className="h-8 w-8 overflow-hidden border border-ink/5 bg-cream">
                                                        {product
                                                            .images?.[0] && (
                                                            <img
                                                                src={
                                                                    product
                                                                        .images[0]
                                                                        .url
                                                                }
                                                                className="h-full w-full object-cover"
                                                            />
                                                        )}
                                                    </div>
                                                    <span className="font-display text-sm text-ink">
                                                        {product.name}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {formatMAD(
                                                    Number(
                                                        product.sale_price ||
                                                            product.base_price,
                                                    ),
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm text-ink-soft">
                                                {product.stock} unités
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    className={`rounded-none border-none ${statusPill[product.status]}`}
                                                >
                                                    {product.status}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>
        </div>
    );
};

ShowSeller.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default ShowSeller;
