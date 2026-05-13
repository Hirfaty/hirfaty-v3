import { Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    Mail,
    MapPin,
    ShoppingBag,
    UserCheck,
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
import type { Order, Store, User } from '@/types';

interface Props {
    user: User & {
        orders: (Order & { store: Store })[];
    };
    orderStats: {
        total: number;
        pending: number;
        delivered: number;
        cancelled: number;
    };
    totalSpent: number;
}

const statusPill: Record<string, string> = {
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

const ShowBuyer = ({ user, orderStats, totalSpent }: Props) => {
    // console.log('orders: ', orderStats);
    // console.log('orders: ', user);
    // console.log('tts: ', totalSpent);
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
                        <Badge variant="secondary" className="rounded-none">
                            Acheteur
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

                <div className="flex items-center gap-3">
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
                {/* Buyer Stats & Info */}
                <aside className="space-y-6 lg:col-span-4">
                    <div className="border border-ink/10 bg-paper p-6">
                        <p className="eyebrow mb-6 text-xs">
                            Informations Profil
                        </p>
                        <div className="space-y-4 text-sm">
                            <div className="flex items-start gap-3">
                                <Mail className="mt-0.5 h-4 w-4 text-ink-soft" />
                                <div>
                                    <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                        Email
                                    </p>
                                    <p className="text-ink">{user.email}</p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <MapPin className="mt-0.5 h-4 w-4 text-ink-soft" />
                                <div>
                                    <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                        Localisation
                                    </p>
                                    <p className="text-ink">
                                        {user.phone_number ||
                                            'Téléphone non renseigné'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="border border-ink/10 bg-paper p-6">
                        <p className="eyebrow mb-6 text-xs">Activité d'achat</p>
                        <div className="space-y-6">
                            <div className="flex items-center gap-4">
                                <div className="flex h-10 w-10 items-center justify-center bg-cream">
                                    <ShoppingBag className="h-5 w-5 text-ink" />
                                </div>
                                <div>
                                    <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                        Dépenses totales
                                    </p>
                                    <p className="font-display text-xl text-ink">
                                        {formatMAD(totalSpent)}
                                    </p>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4 border-t border-ink/5 pt-4">
                                <div>
                                    <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                        Commandes
                                    </p>
                                    <p className="font-display text-lg text-ink">
                                        {orderStats.total}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[10px] font-bold tracking-widest text-ink-soft uppercase">
                                        Livrées
                                    </p>
                                    <p className="font-display text-lg text-emerald-700">
                                        {orderStats.delivered}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>

                {/* Order History */}
                <div className="space-y-8 lg:col-span-8">
                    <div className="overflow-hidden border border-ink/10 bg-paper">
                        <div className="flex items-center justify-between border-b border-ink/10 bg-cream/50 px-6 py-4">
                            <div className="flex items-center gap-2">
                                <ShoppingBag className="h-4 w-4 text-ink" />
                                <h3 className="font-display text-lg text-ink">
                                    Historique des commandes
                                </h3>
                            </div>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="text-[10px] font-bold tracking-widest uppercase">
                                        ID
                                    </TableHead>
                                    <TableHead className="text-[10px] font-bold tracking-widest uppercase">
                                        Boutique
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
                                {user.orders.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="h-32 text-center text-ink-soft italic"
                                        >
                                            Cet utilisateur n'a pas encore passé
                                            de commande.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    user.orders.map((order) => (
                                        <TableRow key={order.id}>
                                            <TableCell className="font-mono text-xs">
                                                #{order.id}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                <Link
                                                    className="underline"
                                                    href={`/store/${order.store.slug}`}
                                                >
                                                    {order.store?.store_name ||
                                                        'N/A'}
                                                </Link>
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

                    <div className="border border-dashed border-ink/10 bg-cream/30 p-6">
                        <div className="flex items-start gap-4">
                            <div className="flex h-10 w-10 items-center justify-center border border-ink/10 bg-paper">
                                <span className="text-xl">✍️</span>
                            </div>
                            <div>
                                <h4 className="mb-1 font-display text-base text-ink">
                                    Note Administrative
                                </h4>
                                <p className="text-xs leading-relaxed text-ink-soft">
                                    Utilisateur identifié comme acheteur
                                    régulier. Pas d'incidents de paiement
                                    signalés. Profil complété à 80%.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

ShowBuyer.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default ShowBuyer;
