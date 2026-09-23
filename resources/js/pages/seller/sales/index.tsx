import { Head, Link } from '@inertiajs/react';
import { Coins, Clock, ShoppingBag } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import SellerLayout from '@/layouts/seller/layout';
import { formatDate } from '@/lib/utils';
import type { Order, PaginatedData } from '@/types';

/**
 * Order money, nothing else.
 *
 * This replaced the earnings screen, which showed gross / platform fee / net
 * payout per order. None of that is displayed any more — every figure here is
 * what the buyer paid for the order.
 */
type Summary = {
    delivered_total: number;
    in_progress_total: number;
    orders_count: number;
};

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

const money = (value: number | string) =>
    `${new Intl.NumberFormat('fr-MA').format(Math.round(Number(value)))} MAD`;

function Stat({
    label,
    value,
    hint,
    icon: Icon,
    accent,
}: {
    label: string;
    value: string;
    hint: string;
    icon: typeof Coins;
    accent: string;
}) {
    return (
        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
            <div className="flex items-center gap-2.5">
                <span
                    className={`inline-flex h-8 w-8 items-center justify-center rounded-lg ${accent}`}
                >
                    <Icon className="h-4 w-4" />
                </span>
                <span className="text-xs font-medium text-ink-soft">
                    {label}
                </span>
            </div>
            <p className="mt-3 text-2xl font-semibold text-ink">{value}</p>
            <p className="mt-1 text-xs text-ink-soft">{hint}</p>
        </div>
    );
}

const Index = ({
    summary,
    orders,
}: {
    summary: Summary;
    orders: PaginatedData<Order>;
}) => {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title={t('titles.seller.sales')} />

            <header>
                <h1 className="text-2xl font-semibold tracking-tight text-ink">
                    Ventes
                </h1>
                <p className="mt-1 text-sm text-ink-soft">
                    Le montant de vos commandes, livrées et en cours.
                </p>
            </header>

            <div className="grid gap-4 sm:grid-cols-3">
                <Stat
                    label="Total livré"
                    value={money(summary.delivered_total)}
                    hint="Commandes arrivées chez l’acheteur"
                    icon={Coins}
                    accent="bg-emerald-50 text-emerald-600"
                />
                <Stat
                    label="En cours"
                    value={money(summary.in_progress_total)}
                    hint="Commandes pas encore livrées"
                    icon={Clock}
                    accent="bg-amber-50 text-amber-600"
                />
                <Stat
                    label="Commandes"
                    value={String(summary.orders_count)}
                    hint="Hors commandes annulées"
                    icon={ShoppingBag}
                    accent="bg-sky-50 text-sky-600"
                />
            </div>

            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b border-border text-left text-xs text-ink-soft">
                            <th className="px-5 py-3 font-medium">Commande</th>
                            <th className="px-5 py-3 font-medium">Acheteur</th>
                            <th className="px-5 py-3 font-medium">Statut</th>
                            <th className="px-5 py-3 font-medium">Date</th>
                            <th className="px-5 py-3 text-end font-medium">
                                Montant
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {orders.data.map((order) => {
                            const meta = ORDER_STATUS[order.status] ?? {
                                label: order.status,
                                className: 'bg-slate-100 text-slate-700',
                            };

                            return (
                                <tr
                                    key={order.id}
                                    className="hover:bg-muted/40"
                                >
                                    <td className="px-5 py-3.5">
                                        <Link
                                            href={route(
                                                'seller.orders.show',
                                                order.reference,
                                            )}
                                            className="font-mono text-xs text-ink underline-offset-2 hover:underline"
                                        >
                                            {order.reference}
                                        </Link>
                                    </td>
                                    <td className="px-5 py-3.5 text-ink">
                                        {order.buyer
                                            ? `${order.buyer.first_name} ${order.buyer.last_name}`
                                            : '—'}
                                    </td>
                                    <td className="px-5 py-3.5">
                                        <span
                                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${meta.className}`}
                                        >
                                            {meta.label}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3.5 text-ink-soft">
                                        {formatDate(order.created_at)}
                                    </td>
                                    <td className="px-5 py-3.5 text-end font-medium text-ink tabular-nums">
                                        {money(order.total_amount)}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>

                {orders.data.length === 0 && (
                    <p className="px-5 py-12 text-center text-sm text-ink-soft">
                        Aucune vente pour l’instant.
                    </p>
                )}
            </div>
        </div>
    );
};

Index.layout = (page: ReactNode) => <SellerLayout>{page}</SellerLayout>;

export default Index;
