import { Link } from '@inertiajs/react';
import { Eye, Search, ShoppingBag } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import SellerLayout from '@/layouts/seller/layout';
import type { Order, OrderStatus, PaginatedData } from '@/types';

const formatDate = (iso: string) =>
    new Date(iso).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

export const ORDER_STATUSES: Array<OrderStatus> = [
    'pending',
    'confirmed',
    'shipped',
    'delivered',
    'cancelled',
];

const statusLabel: Record<OrderStatus, string> = {
    pending: 'En attente',
    confirmed: 'Confirmée',
    shipped: 'Expédiée',
    delivered: 'Livrée',
    cancelled: 'Annulée',
};

const statusClassName: Record<OrderStatus, string> = {
    pending: 'bg-muted text-muted-foreground border-border',
    confirmed: 'bg-blue-500/10 text-blue-700 border-blue-500/30',
    shipped: 'bg-amber-500/10 text-amber-700 border-amber-500/30',
    delivered: 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30',
    cancelled: 'bg-destructive/10 text-destructive border-destructive/30',
};

export default function SellerOrders({
    orders,
}: {
    orders: PaginatedData<Order>;
}) {
    const [query, setQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<OrderStatus | 'all'>(
        'all',
    );

    const filteredOrders = useMemo(() => {
        const q = query.trim().toLowerCase();

        return orders.data.filter((o) => {
            const matchesStatus =
                statusFilter === 'all' || o.status === statusFilter;
            const customerName =
                `${o.buyer.first_name} ${o.buyer.last_name}`.toLowerCase();
            const matchesQuery =
                !q ||
                String(o.id).includes(q) ||
                customerName.includes(q) ||
                o.buyer.email.toLowerCase().includes(q);

            return matchesStatus && matchesQuery;
        });
    }, [orders.data, query, statusFilter]);

    return (
        <div className="mx-auto max-w-[1400px] px-6 py-8 md:px-10">
            <div className="mb-8 flex flex-col gap-4 border-b border-border pb-6 md:flex-row md:items-end md:justify-between">
                <div>
                    <p className="mb-2 text-xs tracking-[0.2em] text-muted-foreground uppercase">
                        Ventes
                    </p>
                    <h1 className="font-display text-4xl leading-none md:text-5xl">
                        Commandes
                    </h1>
                    <p className="mt-3 max-w-md text-muted-foreground">
                        Suivez et gérez vos commandes. {orders.data.length}{' '}
                        commandes au total.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <div className="relative">
                        <Search className="absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="search"
                            placeholder="Rechercher (ID, client, email)"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            className="w-72 rounded-sm ps-10"
                        />
                    </div>
                    <Select
                        value={statusFilter}
                        onValueChange={(v) =>
                            setStatusFilter(v as Order['status'] | 'all')
                        }
                    >
                        <SelectTrigger className="w-48 rounded-sm">
                            <SelectValue placeholder="Statut" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                Tous les statuts
                            </SelectItem>
                            {ORDER_STATUSES.map((s) => (
                                <SelectItem key={s} value={s}>
                                    {statusLabel[s]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="overflow-hidden rounded-sm border border-border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-muted/40 hover:bg-muted/40">
                            <TableHead className="w-[130px] text-[11px] tracking-wider uppercase">
                                N° commande
                            </TableHead>
                            <TableHead className="w-[140px] text-[11px] tracking-wider uppercase">
                                Date
                            </TableHead>
                            <TableHead className="text-[11px] tracking-wider uppercase">
                                Client
                            </TableHead>
                            <TableHead className="w-[90px] text-center text-[11px] tracking-wider uppercase">
                                Articles
                            </TableHead>
                            <TableHead className="w-[140px] text-end text-[11px] tracking-wider uppercase">
                                Total
                            </TableHead>
                            <TableHead className="w-[150px] text-[11px] tracking-wider uppercase">
                                Statut
                            </TableHead>
                            <TableHead className="w-[100px] text-end text-[11px] tracking-wider uppercase">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filteredOrders.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={7}
                                    className="py-16 text-center text-muted-foreground"
                                >
                                    <ShoppingBag className="mx-auto mb-2 h-8 w-8 opacity-50" />
                                    Aucune commande ne correspond à votre
                                    recherche.
                                </TableCell>
                            </TableRow>
                        ) : (
                            filteredOrders.map((o) => {
                                const itemCount = o.items.reduce(
                                    (s, i) => s + i.quantity,
                                    0,
                                );

                                return (
                                    <TableRow
                                        key={o.id}
                                        className="border-border"
                                    >
                                        <TableCell className="font-mono text-xs">
                                            <Link
                                                href={`/seller/orders/${o.id}`}
                                                className="hover:underline"
                                            >
                                                {o.id}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {formatDate(o.created_at)}
                                        </TableCell>
                                        <TableCell>
                                            <div className="font-medium">
                                                {o.buyer.first_name}{' '}
                                                {o.buyer.last_name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {o.buyer.email}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-center tabular-nums">
                                            {itemCount}
                                        </TableCell>
                                        <TableCell className="text-end tabular-nums">
                                            {Number(
                                                o.total_amount,
                                            ).toLocaleString('fr-FR')}{' '}
                                            <span className="text-xs text-muted-foreground">
                                                MAD
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={`rounded-sm font-normal ${statusClassName[o.status]}`}
                                            >
                                                {statusLabel[o.status]}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end">
                                                <Button
                                                    asChild
                                                    size="icon"
                                                    variant="ghost"
                                                    className="h-8 w-8"
                                                    title="Voir détail"
                                                >
                                                    <Link
                                                        href={route(
                                                            'seller.orders.show',
                                                            o.id,
                                                        )}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                );
                            })
                        )}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}

SellerOrders.layout = (page: React.ReactNode) => (
    <SellerLayout children={page} />
);
