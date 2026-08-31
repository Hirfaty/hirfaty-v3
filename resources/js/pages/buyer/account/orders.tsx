import { router } from '@inertiajs/react';
import React from 'react';
import { Badge } from '@/components/ui/badge';
import BuyerLayout from '@/layouts/buyer/layout';
import type { Order, OrderStatus, PaginatedData } from '@/types';

const Orders = ({ orders }: { orders: PaginatedData<Order> }) => {
    const statusColor: Record<OrderStatus, string> = {
        shipped: 'bg-olive/20 text-ink',
        confirmed: 'bg-cream-deep text-ink',
        pending: 'bg-terracotta/15 text-terracotta',
        cancelled: 'bg-ink/10 text-ink-soft',
        delivered: 'bg-cream-deep text-ink',
    };

    const formatOrderDate = (dateString: string) => {
        return `Passée le ${new Intl.DateTimeFormat('fr-FR', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(dateString))}`;
    };

    return (
        <section>
            <h2 className="mb-5 font-display text-2xl text-ink">
                Mes commandes
            </h2>
            <div className="space-y-4">
                {orders.data.map((o) => (
                    <article
                        key={o.id}
                        className="border border-ink/15 bg-paper"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-ink/10 px-5 py-4">
                            <div>
                                <p className="font-mono text-sm text-ink">
                                    {o.reference}
                                </p>
                                <p className="text-xs text-ink-soft">
                                    Passée le {formatOrderDate(o.created_at)}
                                </p>
                            </div>
                            <div className="flex items-center gap-3">
                                {o.status === 'pending' && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (
                                                confirm(
                                                    'Êtes-vous sûr de vouloir annuler cette commande ?',
                                                )
                                            ) {
                                                router.post(
                                                    route(
                                                        'buyer.orders.cancel',
                                                        o.reference,
                                                    ),
                                                );
                                            }
                                        }}
                                        className="rounded-sm border border-rose-500 bg-rose-50 px-3 py-1 text-sm text-rose-700 transition hover:bg-rose-100"
                                    >
                                        Annuler
                                    </button>
                                )}
                                <Badge
                                    className={`rounded-none font-normal ${statusColor[o.status]}`}
                                >
                                    {o.status}
                                </Badge>
                            </div>
                        </div>
                        <div className="space-y-3 p-5">
                            {o.items.map((it, i) => (
                                <div
                                    key={i}
                                    className="flex items-center gap-4"
                                >
                                    {/* <div className="relative h-14 w-14 shrink-0 overflow-hidden">
                            <WarmFill variant={it.variant} className="absolute inset-0" />
                          </div> */}
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-display text-sm text-ink">
                                            {it.product.name}
                                        </p>
                                        <p className="text-xs text-ink-soft">
                                            par {it.product?.store?.store_name}{' '}
                                            · qté {it.quantity}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="flex items-center justify-between border-t border-ink/10 px-5 py-4">
                            <span className="text-sm text-ink-soft">Total</span>
                            <span className="font-display text-xl text-ink">
                                {Number(o.total_amount).toLocaleString('fr-FR')}{' '}
                                <span className="text-xs">MAD</span>
                            </span>
                        </div>
                    </article>
                ))}
            </div>
        </section>
    );
};

Orders.layout = (page: React.ReactNode) => <BuyerLayout children={page} />;
export default Orders;
