import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CreditCard,
    ImageOff,
    MapPin,
    Send,
    Package,
    Phone,
    StickyNote,
    Truck,
    User,
    Wallet,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import CancelOrderDialog from '@/components/admin/cancel-order-dialog';
import type {
    CancellationReason,
    RefundOffer,
} from '@/components/admin/cancel-order-dialog';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type { OrderStatus } from '@/types';
import type { OrderGroup } from './index';

const STATUS_BADGE: Record<OrderStatus, string> = {
    pending: 'bg-slate-100 text-slate-700',
    confirmed: 'bg-amber-100 text-amber-700',
    shipped: 'bg-blue-100 text-blue-700',
    delivered: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-rose-100 text-rose-700',
};

const STATUS_LABEL: Record<OrderStatus, string> = {
    pending: 'En attente',
    confirmed: 'Confirmée',
    shipped: 'Expédiée',
    delivered: 'Livrée',
    cancelled: 'Annulée',
};

/** The moves the desk can make, in the order fulfilment actually happens. */
const ACTIONS: { status: OrderStatus; label: string }[] = [
    { status: 'confirmed', label: 'Confirmer' },
    { status: 'shipped', label: 'Marquer expédiée' },
    { status: 'delivered', label: 'Marquer livrée' },
    { status: 'cancelled', label: 'Annuler' },
];

const currency = (value: number) =>
    new Intl.NumberFormat('fr-MA', {
        style: 'currency',
        currency: 'MAD',
    }).format(value);

/**
 * One purchase, as the fulfilment desk works it.
 *
 * Shows every piece across every workshop in the basket, because the desk
 * collects the lot and delivers it to one address. Status and tracking apply
 * to the whole purchase by default; the per-workshop checkbox is there for the
 * exception where one artisan's piece has to travel on its own.
 */
/** The Payzone or PayPal transaction behind a purchase, as Admin\OrderController::show sends it. */
type CardPayment = {
    provider: 'payzone' | 'paypal';
    charge_id: string;
    /** Payzone's transaction, or PayPal's capture id — what to search for a refund. */
    transaction_id: string | null;
    card_brand: string | null;
    /** What the provider took, in `currency` — MAD for Payzone, EUR/USD… for PayPal. */
    amount: number;
    currency: string;
    /** MAD → `currency`; null when charged in MAD. */
    exchange_rate: number | null;
    status: string;
    paid_at: string | null;
    /** Whether a refund goes through the provider's API at once, or by hand. */
    auto_refund: boolean;
};

const Show = ({
    group,
    payment,
    cancellationReasons,
}: {
    group: OrderGroup;
    payment: CardPayment | null;
    cancellationReasons: CancellationReason[];
}) => {
    const { t } = useTranslation();
    const paidOnline =
        (group.payment_method === 'card' ||
            group.payment_method === 'paypal') &&
        group.payment_status === 'paid';
    const [tracking, setTracking] = useState(
        group.orders[0]?.tracking_number ?? '',
    );

    /*
     * Which workshop's parcel an action applies to. `null` — the default — is
     * the whole purchase. Selecting one sends `scope: 'single'`, which is what
     * splits the group's statuses apart.
     */
    const [separateOrderId, setSeparateOrderId] = useState<number | null>(null);

    /*
     * Shown to the buyer on their tracking page ("arrivé au centre de tri de
     * Casablanca"). Sent with a status change, or on its own when the parcel
     * moved without changing step.
     */
    const [note, setNote] = useState('');

    const target = separateOrderId
        ? group.orders.find((o) => o.id === separateOrderId)
        : null;

    /*
     * "Annuler" opens a dialog instead of acting at once: the desk says why,
     * and decides on the money (CancelOrderDialog).
     */
    const [cancelling, setCancelling] = useState(false);

    /*
     * What ticking "refund" would give back, in the currency the provider
     * took: the whole payment when the whole purchase goes and nothing was
     * refunded before, else the cancelled orders' share at the rate the
     * attempt was priced at — the same rule as RefundService.
     */
    const refundOffer = ((): RefundOffer | null => {
        const inScope = target ? [target] : group.orders;
        const paid = inScope.filter((o) => o.payment_status === 'paid');

        if (
            !payment ||
            payment.status !== 'paid' ||
            paid.length === 0 ||
            (group.payment_method !== 'card' &&
                group.payment_method !== 'paypal')
        ) {
            return null;
        }

        const whole =
            group.orders.every((o) => o.payment_status !== 'refunded') &&
            group.orders
                .filter((o) => o.payment_status === 'paid')
                .every((o) => paid.includes(o));
        const mad = paid.reduce(
            (sum, o) => sum + o.total_amount + o.delivery_fee,
            0,
        );
        const amount = whole
            ? payment.amount
            : Math.min(
                  payment.amount,
                  Math.round(mad * (payment.exchange_rate ?? 1) * 100) / 100,
              );

        return {
            provider: payment.provider,
            automatic: payment.auto_refund,
            amount: new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: payment.currency,
            }).format(amount),
        };
    })();

    const apply = (status: OrderStatus) => {
        const reference = target?.reference ?? group.primary_reference;

        router.patch(
            route('admin.orders.status', reference),
            {
                status,
                tracking_number: tracking || null,
                scope: target ? 'single' : 'group',
                note: note.trim() || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNote('');
                    toast.success(
                        target
                            ? `${target.store.name} : ${STATUS_LABEL[status].toLowerCase()}`
                            : `Achat ${STATUS_LABEL[status].toLowerCase()}`,
                    );
                },
                onError: (errors) =>
                    toast.error(
                        (Object.values(errors)[0] as string) ??
                            'Mise à jour impossible.',
                    ),
            },
        );
    };

    const publishNote = () => {
        const reference = target?.reference ?? group.primary_reference;

        router.post(
            route('admin.orders.notes.store', reference),
            { note: note.trim(), scope: target ? 'single' : 'group' },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNote('');
                    toast.success('Mise à jour du suivi publiée');
                },
                onError: (errors) =>
                    toast.error(
                        (Object.values(errors)[0] as string) ??
                            'Publication impossible.',
                    ),
            },
        );
    };

    const itemCount = group.orders.reduce(
        (sum, order) =>
            sum + order.items.reduce((n, item) => n + item.quantity, 0),
        0,
    );

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title={`${t('titles.admin.order')} ${group.reference}`} />

            <header className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link
                        href={route('admin.orders.index')}
                        className="inline-flex items-center gap-1.5 text-sm text-ink-soft transition hover:text-ink"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Commandes
                    </Link>
                    <h1 className="mt-2 text-2xl font-semibold tracking-tight text-ink">
                        Achat {group.reference}
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        {itemCount} article{itemCount > 1 ? 's' : ''} ·{' '}
                        {group.orders.length} atelier
                        {group.orders.length > 1 ? 's' : ''} ·{' '}
                        {formatDate(group.created_at)}
                    </p>
                </div>

                {group.status ? (
                    <span
                        className={`inline-flex items-center rounded-full px-3 py-1.5 text-sm font-medium ${STATUS_BADGE[group.status]}`}
                    >
                        {STATUS_LABEL[group.status]}
                    </span>
                ) : (
                    <span className="inline-flex items-center rounded-full bg-violet-100 px-3 py-1.5 text-sm font-medium text-violet-700">
                        Colis séparés
                    </span>
                )}
            </header>

            <div className="grid gap-6 lg:grid-cols-[1fr_20rem] lg:items-start">
                {/* ── Left: what to collect, from whom ─────────── */}
                <div className="flex flex-col gap-4">
                    {group.orders.map((order) => (
                        <div
                            key={order.id}
                            className="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/40 px-5 py-3">
                                <div>
                                    <p className="font-medium text-ink">
                                        {order.store.name}
                                    </p>
                                    <p className="text-xs text-ink-soft">
                                        {order.reference}
                                        {order.tracking_number &&
                                            ` · Suivi ${order.tracking_number}`}
                                    </p>
                                </div>
                                <span
                                    className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_BADGE[order.status]}`}
                                >
                                    {STATUS_LABEL[order.status]}
                                </span>
                            </div>

                            {/* Why it was cancelled, and what became of the money. */}
                            {order.status === 'cancelled' &&
                                (order.cancellation.length > 0 ||
                                    order.cancellation_refund !== null) && (
                                    <div className="border-b border-border bg-rose-50/60 px-5 py-2.5 text-xs leading-relaxed text-rose-800">
                                        {order.cancellation.length > 0 && (
                                            <p>
                                                Motif :{' '}
                                                {order.cancellation.join(' · ')}
                                            </p>
                                        )}
                                        {order.cancellation_refund !== null && (
                                            <p className="mt-0.5">
                                                {order.cancellation_refund
                                                    ? order.payment_status ===
                                                      'refunded'
                                                        ? 'Remboursée'
                                                        : 'Remboursement demandé'
                                                    : 'Annulée sans remboursement'}
                                            </p>
                                        )}
                                    </div>
                                )}

                            <ul className="divide-y divide-border">
                                {order.items.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex items-center gap-4 px-5 py-3.5"
                                    >
                                        <span className="h-14 w-14 shrink-0 overflow-hidden rounded-md border border-border bg-muted">
                                            {item.image ? (
                                                <img
                                                    src={item.image}
                                                    alt={item.name}
                                                    loading="lazy"
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                <span className="flex h-full w-full items-center justify-center">
                                                    <ImageOff className="h-5 w-5 text-ink-soft/40" />
                                                </span>
                                            )}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium text-ink">
                                                {item.name}
                                            </p>
                                            {item.variant_label && (
                                                <p className="truncate text-xs text-ink-soft">
                                                    {item.variant_label}
                                                </p>
                                            )}
                                            <p className="mt-0.5 text-xs text-ink-soft">
                                                Quantité : {item.quantity}
                                            </p>
                                        </div>
                                        <span className="shrink-0 text-sm font-medium text-ink tabular-nums">
                                            {currency(item.subtotal)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>

                {/* ── Right: where it goes, and what to do ──────── */}
                <div className="flex flex-col gap-4">
                    <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <h2 className="text-sm font-semibold text-ink">
                            Client
                        </h2>
                        <dl className="mt-3 space-y-2.5 text-sm">
                            <div className="flex items-start gap-2.5">
                                <User className="mt-0.5 h-4 w-4 shrink-0 text-ink-soft/60" />
                                <div>
                                    <p className="text-ink">
                                        {group.buyer.name}
                                    </p>
                                    {group.buyer.email && (
                                        <p className="text-xs text-ink-soft">
                                            {group.buyer.email}
                                        </p>
                                    )}
                                </div>
                            </div>
                            {group.shipping_phone && (
                                <div className="flex items-start gap-2.5">
                                    <Phone className="mt-0.5 h-4 w-4 shrink-0 text-ink-soft/60" />
                                    <p className="text-ink">
                                        {group.shipping_phone}
                                    </p>
                                </div>
                            )}
                            <div className="flex items-start gap-2.5">
                                <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-ink-soft/60" />
                                <p className="text-ink">
                                    {group.shipping_address}
                                </p>
                            </div>
                            {group.notes && (
                                <div className="flex items-start gap-2.5">
                                    <StickyNote className="mt-0.5 h-4 w-4 shrink-0 text-ink-soft/60" />
                                    <p className="text-ink-soft italic">
                                        {group.notes}
                                    </p>
                                </div>
                            )}
                        </dl>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <h2 className="text-sm font-semibold text-ink">
                            Total
                        </h2>
                        <dl className="mt-3 space-y-2 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-ink-soft">Produits</dt>
                                <dd className="text-ink tabular-nums">
                                    {currency(group.products_total)}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-ink-soft">
                                    Livraison{' '}
                                    {group.delivery_service_name ?? 'Amana'}
                                </dt>
                                <dd className="text-ink tabular-nums">
                                    {currency(group.delivery_total)}
                                </dd>
                            </div>
                            <div className="flex justify-between border-t border-border pt-2 font-medium">
                                <dt className="text-ink">
                                    {paidOnline
                                        ? group.payment_method === 'paypal'
                                            ? 'Payé avec PayPal'
                                            : 'Payé par carte'
                                        : 'À encaisser'}
                                </dt>
                                <dd className="text-ink tabular-nums">
                                    {currency(
                                        group.products_total +
                                            group.delivery_total,
                                    )}
                                </dd>
                            </div>
                        </dl>

                        {/*
                         * Paid online: the courier collects nothing. A refund
                         * (after a cancellation) goes through the provider's
                         * API; the references below are for finding the
                         * payment in Payzone's back-office or PayPal's
                         * dashboard when it has to be done by hand.
                         */}
                        {group.payment_method === 'paypal' && (
                            <div className="mt-3 space-y-1 rounded-lg bg-emerald-50 px-3 py-2.5 text-xs leading-relaxed text-emerald-800">
                                <p className="flex items-center gap-1.5 font-medium">
                                    <Wallet className="h-3.5 w-3.5" />
                                    {group.payment_status === 'refunded'
                                        ? 'PayPal — remboursé'
                                        : 'Payée avec PayPal — rien à encaisser'}
                                </p>
                                {payment && (
                                    <>
                                        <p className="tabular-nums">
                                            Débité par PayPal :{' '}
                                            {new Intl.NumberFormat('fr-FR', {
                                                style: 'currency',
                                                currency: payment.currency,
                                            }).format(payment.amount)}
                                        </p>
                                        {payment.card_brand && (
                                            <p>
                                                Carte via PayPal :{' '}
                                                {payment.card_brand}
                                            </p>
                                        )}
                                        {payment.transaction_id && (
                                            <p className="break-all">
                                                Transaction PayPal :{' '}
                                                {payment.transaction_id}
                                            </p>
                                        )}
                                        {/* Sent to PayPal as its "Invoice ID"
                                            — a payment-attempt id, not
                                            Hirfaty's invoice number, which
                                            is the purchase reference. */}
                                        <p className="break-all">
                                            Réf. paiement (« Invoice ID » chez
                                            PayPal) : {payment.charge_id}
                                        </p>
                                        {payment.paid_at && (
                                            <p>
                                                Payée le{' '}
                                                {formatDate(payment.paid_at)}
                                            </p>
                                        )}
                                    </>
                                )}
                            </div>
                        )}
                        {group.payment_method === 'card' && (
                            <div className="mt-3 space-y-1 rounded-lg bg-emerald-50 px-3 py-2.5 text-xs leading-relaxed text-emerald-800">
                                <p className="flex items-center gap-1.5 font-medium">
                                    <CreditCard className="h-3.5 w-3.5" />
                                    {group.payment_status === 'refunded'
                                        ? 'Carte — remboursée'
                                        : 'Payée par carte (Payzone) — rien à encaisser'}
                                </p>
                                {payment && (
                                    <>
                                        {payment.card_brand && (
                                            <p>Carte : {payment.card_brand}</p>
                                        )}
                                        <p className="break-all">
                                            Charge Payzone : {payment.charge_id}
                                        </p>
                                        {payment.transaction_id && (
                                            <p className="break-all">
                                                Transaction :{' '}
                                                {payment.transaction_id}
                                            </p>
                                        )}
                                        {payment.paid_at && (
                                            <p>
                                                Payée le{' '}
                                                {formatDate(payment.paid_at)}
                                            </p>
                                        )}
                                    </>
                                )}
                            </div>
                        )}

                        {/*
                         * What the buyer was reading when they ordered, using
                         * the rate stored on the order rather than a live one
                         * — re-converting at today's rate would misreport what
                         * was actually quoted. Absent for MAD buyers and for
                         * orders placed before this was tracked.
                         */}
                        {group.display_currency &&
                            group.display_currency !== 'MAD' && (
                                <p className="mt-3 border-t border-border pt-3 text-xs leading-relaxed text-ink-soft">
                                    L’acheteur consultait le site en{' '}
                                    <span className="font-medium text-ink">
                                        {group.display_currency}
                                    </span>{' '}
                                    — soit environ{' '}
                                    <span className="font-medium text-ink tabular-nums">
                                        {new Intl.NumberFormat('fr-FR', {
                                            maximumFractionDigits: 2,
                                        }).format(
                                            (group.products_total +
                                                group.delivery_total) *
                                                Number(group.display_rate ?? 0),
                                        )}{' '}
                                        {group.display_currency}
                                    </span>{' '}
                                    au taux du jour de la commande. Le montant
                                    encaissé reste en dirhams.
                                </p>
                            )}
                    </div>

                    {/* ── Fulfilment ──────────────────────────── */}
                    <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <h2 className="text-sm font-semibold text-ink">
                            Traitement
                        </h2>

                        <label className="mt-3 block">
                            <span className="mb-1.5 block text-xs text-ink-soft">
                                Numéro de suivi{' '}
                                {group.delivery_service_name ?? 'Amana'}
                            </span>
                            <div className="relative">
                                <Truck className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft/50" />
                                <input
                                    value={tracking}
                                    onChange={(e) =>
                                        setTracking(e.target.value)
                                    }
                                    placeholder="AMN-…"
                                    className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                                />
                            </div>
                        </label>

                        <label className="mt-4 block">
                            <span className="mb-1.5 block text-xs text-ink-soft">
                                Note de suivi (visible par l’acheteur)
                            </span>
                            <textarea
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                rows={2}
                                maxLength={500}
                                placeholder="Ex. : Colis arrivé au centre de tri de Casablanca"
                                className="w-full resize-none rounded-lg border border-border bg-card px-3 py-2.5 text-sm text-ink transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                            />
                            <span className="mt-1 block text-xs text-ink-soft">
                                Jointe au prochain changement de statut, ou
                                publiée seule ci-dessous.
                            </span>
                        </label>

                        {/*
                         * The exception, not the rule: normally the desk picks
                         * everything up at once and one tracking number covers
                         * the purchase. Ticking a workshop here sends
                         * `scope: single`, so only that artisan's parcel moves.
                         */}
                        {group.orders.length > 1 && (
                            <div className="mt-4 rounded-lg border border-border bg-muted/30 p-3.5">
                                <p className="text-xs font-medium text-ink">
                                    Expédier un atelier séparément
                                </p>
                                <p className="mt-1 text-xs text-ink-soft">
                                    Par défaut, l’action ci-dessous s’applique à
                                    tout l’achat.
                                </p>
                                <div className="mt-2.5 space-y-1.5">
                                    {group.orders.map((order) => (
                                        <label
                                            key={order.id}
                                            className="flex cursor-pointer items-center gap-2 text-sm text-ink"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={
                                                    separateOrderId === order.id
                                                }
                                                onChange={(e) =>
                                                    setSeparateOrderId(
                                                        e.target.checked
                                                            ? order.id
                                                            : null,
                                                    )
                                                }
                                                className="h-4 w-4 rounded border-border text-ink focus:ring-ink/20"
                                            />
                                            {order.store.name}
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}

                        <p className="mt-4 text-xs text-ink-soft">
                            {target
                                ? `S’applique à : ${target.store.name}`
                                : 'S’applique à tout l’achat'}
                        </p>

                        <div className="mt-2 grid gap-2">
                            {ACTIONS.map((action) => (
                                <button
                                    key={action.status}
                                    type="button"
                                    onClick={() =>
                                        action.status === 'cancelled'
                                            ? setCancelling(true)
                                            : apply(action.status)
                                    }
                                    className={`inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition ${
                                        action.status === 'cancelled'
                                            ? 'border border-rose-200 text-rose-700 hover:bg-rose-50'
                                            : 'bg-ink text-white hover:bg-ink/90'
                                    }`}
                                >
                                    <Package className="h-4 w-4" />
                                    {action.label}
                                </button>
                            ))}
                            <button
                                type="button"
                                onClick={publishNote}
                                disabled={note.trim() === ''}
                                className="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-ink transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                <Send className="h-4 w-4" />
                                Publier la note seule
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <CancelOrderDialog
                open={cancelling}
                onOpenChange={setCancelling}
                reference={target?.reference ?? group.primary_reference}
                scope={target ? 'single' : 'group'}
                appliesTo={target ? target.store.name : 'tout l’achat'}
                reasons={cancellationReasons}
                refund={refundOffer}
            />
        </div>
    );
};

Show.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Show;
