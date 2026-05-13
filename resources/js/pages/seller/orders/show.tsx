import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Mail, MapPin, Phone, Save } from 'lucide-react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import SellerLayout from '@/layouts/seller/layout';
import type { Order, OrderStatus } from '@/types';

const formatDateTime = (iso: string) =>
    new Date(iso).toLocaleString('fr-FR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

const ORDER_STATUSES: Array<OrderStatus> = [
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

function SellerOrderDetail({ order }: { order: Order }) {
    const { data, setData, processing, errors } = useForm<{
        status: OrderStatus;
        tracking_number: string;
    }>({
        status: order.status,
        tracking_number: order.tracking_number ?? '',
    });

    const total = Number(order.total_amount || 0);
    const dirty =
        data.status !== order.status ||
        data.tracking_number !== (order.tracking_number ?? '');

    const handleSave = () => {
        router.patch(
            route('seller.orders.status', order.id),
            {
                status: data.status,
                tracking_number: data.tracking_number || null,
            },
            {
                onSuccess: () => {
                    toast.success('Statut mis à jour', {
                        description: `Commande ${order.id} -> ${statusLabel[data.status]}`,
                    });
                },
            },
        );
    };

    const itemSubtotal = (subtotal: string, unitPrice: string, qty: number) => {
        const parsedSubtotal = Number(subtotal);

        if (!Number.isNaN(parsedSubtotal) && parsedSubtotal > 0) {
            return parsedSubtotal;
        }

        return Number(unitPrice) * qty;
    };

    const buyerName = `${order.buyer.first_name} ${order.buyer.last_name}`;
    const cityLabel = order.city?.name ?? 'Ville non precisee';

    return (
        <div className="mx-auto max-w-[1200px] px-6 py-8 md:px-10">
            <div className="mb-6">
                <Button
                    asChild
                    variant="ghost"
                    size="sm"
                    className="-ms-2 mb-3 rounded-sm"
                >
                    <Link href={route('seller.orders.index')}>
                        <ArrowLeft className="me-2 h-4 w-4" />
                        Toutes les commandes
                    </Link>
                </Button>
                <div className="flex flex-col gap-4 border-b border-border pb-6 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="mb-2 text-xs tracking-[0.2em] text-muted-foreground uppercase">
                            Commande
                        </p>
                        <h1 className="font-display text-4xl leading-none md:text-5xl">
                            #{order.id}
                        </h1>
                        <p className="mt-3 text-muted-foreground">
                            Passee le {formatDateTime(order.created_at)}
                        </p>
                    </div>
                    <Badge
                        variant="outline"
                        className={`rounded-sm px-3 py-1 text-sm font-normal ${statusClassName[data.status]}`}
                    >
                        {statusLabel[data.status]}
                    </Badge>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card className="rounded-sm">
                        <CardHeader>
                            <CardTitle className="font-display text-xl">
                                Articles commandes
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-muted/40 hover:bg-muted/40">
                                        <TableHead className="text-[11px] tracking-wider uppercase">
                                            Produit
                                        </TableHead>
                                        <TableHead className="w-[100px] text-[11px] tracking-wider uppercase">
                                            ID
                                        </TableHead>
                                        <TableHead className="w-[80px] text-center text-[11px] tracking-wider uppercase">
                                            Qte
                                        </TableHead>
                                        <TableHead className="w-[120px] text-end text-[11px] tracking-wider uppercase">
                                            P.U.
                                        </TableHead>
                                        <TableHead className="w-[140px] text-end text-[11px] tracking-wider uppercase">
                                            Sous-total
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {order.items.map((item) => (
                                        <TableRow
                                            key={item.id}
                                            className="border-border"
                                        >
                                            <TableCell className="font-medium">
                                                {item.product?.name ?? 'Produit'}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs text-muted-foreground">
                                                {item.product_id}
                                            </TableCell>
                                            <TableCell className="text-center tabular-nums">
                                                {item.quantity}
                                            </TableCell>
                                            <TableCell className="text-end tabular-nums">
                                                {Number(
                                                    item.unit_price,
                                                ).toLocaleString('fr-FR')}{' '}
                                                MAD
                                            </TableCell>
                                            <TableCell className="text-end font-medium tabular-nums">
                                                {itemSubtotal(
                                                    item.subtotal,
                                                    item.unit_price,
                                                    item.quantity,
                                                ).toLocaleString('fr-FR')}{' '}
                                                MAD
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <Separator />
                            <div className="flex justify-end p-4">
                                <div className="w-full max-w-xs space-y-1.5">
                                    <div className="flex justify-between text-sm text-muted-foreground">
                                        <span>Total commande</span>
                                        <span className="tabular-nums">
                                            {total.toLocaleString('fr-FR')} MAD
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm text-muted-foreground">
                                        <span>Frais plateforme</span>
                                        <span className="tabular-nums">
                                            {Number(
                                                order.platform_fee,
                                            ).toLocaleString('fr-FR')}{' '}
                                            MAD
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm text-muted-foreground">
                                        <span>Frais livraison</span>
                                        <span className="tabular-nums">
                                            {Number(order.amana_fee).toLocaleString(
                                                'fr-FR',
                                            )}{' '}
                                            MAD
                                        </span>
                                    </div>
                                    <Separator className="my-2" />
                                    <div className="flex justify-between font-display text-lg">
                                        <span>Gain vendeur</span>
                                        <span className="tabular-nums">
                                            {Number(
                                                order.seller_earnings,
                                            ).toLocaleString('fr-FR')}{' '}
                                            MAD
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {order.notes && (
                        <Card className="rounded-sm">
                            <CardHeader>
                                <CardTitle className="font-display text-xl">
                                    Note du client
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground italic">
                                    "{order.notes}"
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <div className="space-y-6">
                    <Card className="rounded-sm">
                        <CardHeader>
                            <CardTitle className="font-display text-xl">
                                Mettre a jour le statut
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label>Statut de la commande</Label>
                                <Select
                                    value={data.status}
                                    onValueChange={(v) =>
                                        setData('status', v as OrderStatus)
                                    }
                                >
                                    <SelectTrigger className="rounded-sm">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {ORDER_STATUSES.map((s) => (
                                            <SelectItem key={s} value={s}>
                                                {statusLabel[s]}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            {data.status === 'shipped' && (
                                <div className="space-y-2">
                                    <Label htmlFor="tracking-number">
                                        Numero de suivi
                                    </Label>
                                    <Input
                                        id="tracking-number"
                                        value={data.tracking_number}
                                        onChange={(e) =>
                                            setData(
                                                'tracking_number',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Ex: AMN123456789"
                                        className="rounded-sm"
                                    />
                                    {errors.tracking_number && (
                                        <p className="text-xs text-destructive">
                                            {errors.tracking_number}
                                        </p>
                                    )}
                                </div>
                            )}
                            {errors.status && (
                                <p className="text-xs text-destructive">
                                    {errors.status}
                                </p>
                            )}
                            <Button
                                onClick={handleSave}
                                disabled={!dirty || processing}
                                className="w-full rounded-sm"
                            >
                                <Save className="me-2 h-4 w-4" />
                                {processing ? 'Enregistrement...' : 'Enregistrer'}
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="rounded-sm">
                        <CardHeader>
                            <CardTitle className="font-display text-xl">
                                Client
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div className="font-medium">{buyerName}</div>
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <Mail className="h-3.5 w-3.5" />
                                <a
                                    href={`mailto:${order.buyer.email}`}
                                    className="hover:text-foreground"
                                >
                                    {order.buyer.email}
                                </a>
                            </div>
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <Phone className="h-3.5 w-3.5" />
                                {order.buyer.phone_number}
                            </div>
                            <Separator />
                            <div className="flex items-start gap-2 text-muted-foreground">
                                <MapPin className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <div>
                                    <div>{order.shipping_address}</div>
                                    <div>{cityLabel}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="rounded-sm">
                        <CardHeader>
                            <CardTitle className="font-display text-xl">
                                Livraison
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            {order.tracking_number
                                ? `Numero de suivi: ${order.tracking_number}`
                                : 'Aucun numero de suivi'}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}

SellerOrderDetail.layout = (page: React.ReactNode) => (
    <SellerLayout children={page} />
);

export default SellerOrderDetail;
