import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ImageOff,
    MapPin,
    Package,
    Phone,
    StickyNote,
    Truck,
    User,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
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
const Show = ({ group }: { group: OrderGroup }) => {
    const [tracking, setTracking] = useState(
        group.orders[0]?.tracking_number ?? '',
    );

    /*
     * Which workshop's parcel an action applies to. `null` — the default — is
     * the whole purchase. Selecting one sends `scope: 'single'`, which is what
     * splits the group's statuses apart.
     */
    const [separateOrderId, setSeparateOrderId] = useState<number | null>(null);

    const target = separateOrderId
        ? group.orders.find((o) => o.id === separateOrderId)
        : null;

    const apply = (status: OrderStatus) => {
        const reference = target?.reference ?? group.primary_reference;

        router.patch(
            route('admin.orders.status', reference),
            {
                status,
                tracking_number: tracking || null,
                scope: target ? 'single' : 'group',
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success(
                        target
                            ? `${target.store.name} : ${STATUS_LABEL[status].toLowerCase()}`
                            : `Achat ${STATUS_LABEL[status].toLowerCase()}`,
                    ),
                onError: (errors) =>
                    toast.error(
                        (Object.values(errors)[0] as string) ??
                            'Mise à jour impossible.',
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
            <Head title={`Achat ${group.reference}`} />

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
                                    Livraison Amana
                                </dt>
                                <dd className="text-ink tabular-nums">
                                    {currency(group.delivery_total)}
                                </dd>
                            </div>
                            <div className="flex justify-between border-t border-border pt-2 font-medium">
                                <dt className="text-ink">À encaisser</dt>
                                <dd className="text-ink tabular-nums">
                                    {currency(
                                        group.products_total +
                                            group.delivery_total,
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {/* ── Fulfilment ──────────────────────────── */}
                    <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <h2 className="text-sm font-semibold text-ink">
                            Traitement
                        </h2>

                        <label className="mt-3 block">
                            <span className="mb-1.5 block text-xs text-ink-soft">
                                Numéro de suivi Amana
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
                                    onClick={() => apply(action.status)}
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

Show.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Show;
