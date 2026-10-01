import { Link } from '@inertiajs/react';
import {
    Copy,
    Download,
    History,
    LifeBuoy,
    MoreVertical,
    Star,
} from 'lucide-react';
import { toast } from 'sonner';
import EmptyShowcase from '@/components/buyer/EmptyShowcase';
import OrderStatusText, {
    STATUS_COLOURS,
} from '@/components/buyer/OrderStatusText';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCurrency } from '@/hooks/use-currency';
import { useTranslation } from '@/hooks/use-translation';
import BuyerAccountLayout from '@/layouts/buyer/account-layout';
import type { BuyerPurchase } from '@/types/orders';

const LOCALE_TAGS: Record<string, string> = {
    fr: 'fr-MA',
    ar: 'ar-MA',
    en: 'en-MA',
};

/**
 * The order history: one card per purchase.
 *
 * A basket spanning three workshops is three `Order` rows in the database —
 * that is who gets paid and who gets notified — but the buyer bought one
 * thing, so the card is the checkout: one reference, one status, one total,
 * one invoice, and every piece from every workshop in a single grid.
 *
 * The split survives only where it is genuinely per workshop: each piece says
 * which atelier made it, and a shop that has moved on from the rest shows its
 * own status. History and ratings each have their own page (tracking.tsx,
 * review.tsx), linked from the foot of the card.
 */
export default function BuyerOrders({
    purchases,
}: {
    purchases: BuyerPurchase[];
}) {
    const { t, locale } = useTranslation();
    const tag = LOCALE_TAGS[locale as string] ?? 'fr-MA';

    /*
     * Prices arrive from the server in MAD. `money()` renders them in the
     * currency this visitor is reading (their own pick, or their IP's) and
     * appends the currency itself — so nothing here should add a suffix.
     */
    const { money } = useCurrency();

    const date = (value: string | null) =>
        value
            ? new Intl.DateTimeFormat(tag, {
                  weekday: 'short',
                  day: 'numeric',
                  month: 'short',
                  year: 'numeric',
              }).format(new Date(value))
            : null;

    const placedAt = (value: string | null) => {
        if (!value) {
            return null;
        }

        const at = new Date(value);
        const time = new Intl.DateTimeFormat(tag, {
            hour: '2-digit',
            minute: '2-digit',
        }).format(at);
        const day = new Intl.DateTimeFormat(tag, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        }).format(at);

        return `${time}, ${day}`;
    };

    const copyReference = (reference: string) => {
        navigator.clipboard
            ?.writeText(reference)
            .then(() => toast.success(t('buyer.orders.reference_copied')))
            .catch(() => undefined);
    };

    return (
        <BuyerAccountLayout
            active="orders"
            title={t('buyer.account.nav_orders')}
            description={t('buyer.account.orders_intro')}
        >
            {purchases.length === 0 ? (
                <EmptyShowcase
                    eyebrow={t('buyer.orders.empty_eyebrow')}
                    title={t('buyer.orders.empty_title')}
                    ctaLabel={t('buyer.orders.empty_cta')}
                />
            ) : (
                <ul className="space-y-5">
                    {purchases.map((purchase) => {
                        const split = purchase.orders.length > 1;
                        const address = [
                            purchase.shipping_address,
                            purchase.city,
                        ]
                            .filter(Boolean)
                            .join(', ');
                        const byline = [
                            purchase.items_count === 1
                                ? t('buyer.orders.products_one')
                                : t('buyer.orders.products_many').replace(
                                      ':count',
                                      String(purchase.items_count),
                                  ),
                            purchase.buyer_name &&
                                t('buyer.orders.by').replace(
                                    ':name',
                                    purchase.buyer_name,
                                ),
                            placedAt(purchase.created_at),
                        ].filter(Boolean);

                        return (
                            <li
                                key={purchase.reference}
                                className="rounded-2xl bg-white p-5 shadow-xl shadow-souk-brown/5 sm:p-6"
                            >
                                {/* ── Header: reference, byline, actions ── */}
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        {/* Tabular figures so a reference
                                            can be matched digit for digit
                                            against the confirmation email. */}
                                        <h2 className="font-display text-lg text-souk-title tabular-nums">
                                            {t('buyer.orders.number').replace(
                                                ':reference',
                                                '',
                                            )}
                                            <bdi
                                                dir="ltr"
                                                className="font-sans"
                                            >
                                                {purchase.reference}
                                            </bdi>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    copyReference(
                                                        purchase.reference,
                                                    )
                                                }
                                                aria-label={t(
                                                    'buyer.orders.copy_reference',
                                                )}
                                                title={t(
                                                    'buyer.orders.copy_reference',
                                                )}
                                                className="ms-2 inline-flex h-6 w-6 cursor-pointer items-center justify-center rounded-md align-middle text-souk-brown-deep/50 transition-colors hover:bg-souk-peach hover:text-souk-title"
                                            >
                                                <Copy
                                                    className="h-3.5 w-3.5"
                                                    strokeWidth={1.8}
                                                />
                                            </button>
                                        </h2>
                                        <p className="mt-1 text-sm text-souk-brown-deep/60">
                                            {byline.map((part, index) => (
                                                <span key={index}>
                                                    {index > 0 && (
                                                        <span
                                                            aria-hidden
                                                            className="mx-2 text-souk-brown-deep/25"
                                                        >
                                                            |
                                                        </span>
                                                    )}
                                                    {part}
                                                </span>
                                            ))}
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        {/* A plain anchor, not an Inertia
                                            Link: the response is a PDF
                                            attachment, not a page. */}
                                        <a
                                            href={route(
                                                'buyer.purchases.invoice',
                                                purchase.reference,
                                            )}
                                            className="inline-flex items-center gap-2 rounded-lg border border-souk-brown/20 px-3.5 py-2 text-sm font-medium text-souk-title transition-colors hover:bg-souk-peach"
                                        >
                                            <Download
                                                className="h-4 w-4"
                                                strokeWidth={1.8}
                                            />
                                            {t('buyer.orders.download_invoice')}
                                        </a>

                                        <DropdownMenu>
                                            <DropdownMenuTrigger
                                                aria-label={t(
                                                    'buyer.orders.more_actions',
                                                )}
                                                className="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-souk-brown/20 text-souk-title transition-colors hover:bg-souk-peach"
                                            >
                                                <MoreVertical
                                                    className="h-4 w-4"
                                                    strokeWidth={1.8}
                                                />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={route('contact')}
                                                    >
                                                        <LifeBuoy className="h-4 w-4" />
                                                        {t(
                                                            'buyer.orders.contact',
                                                        )}
                                                    </Link>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>

                                {/* ── Facts: status, delivery, address, total ── */}
                                <dl className="mt-5 grid grid-cols-[auto_minmax(0,1fr)] gap-x-8 gap-y-2 border-t border-souk-brown/10 pt-5 text-sm">
                                    <dt className="text-souk-brown-deep/60">
                                        {t('buyer.orders.status')}
                                    </dt>
                                    <dd>
                                        <OrderStatusText
                                            status={purchase.status}
                                        />
                                    </dd>

                                    <dt className="text-souk-brown-deep/60">
                                        {t('buyer.orders.delivery_date')}
                                    </dt>
                                    <dd className="text-souk-brown-deep/85">
                                        {date(purchase.delivered_at) ??
                                            t('buyer.orders.delivery_pending')}
                                    </dd>

                                    {address && (
                                        <>
                                            <dt className="text-souk-brown-deep/60">
                                                {t('buyer.orders.delivered_to')}
                                            </dt>
                                            <dd className="text-souk-brown-deep/85">
                                                {address}
                                            </dd>
                                        </>
                                    )}

                                    <dt className="font-semibold text-souk-title">
                                        {t('buyer.orders.total')}
                                    </dt>
                                    <dd className="text-souk-title">
                                        <span className="font-display font-semibold tabular-nums">
                                            {money(purchase.total_amount)} TTC
                                        </span>
                                        {/* Delivery is charged once for the
                                            basket, not per piece — without
                                            this the total would look larger
                                            than the pieces below add up to. */}
                                        {Number(purchase.delivery_fee) > 0 && (
                                            <span className="ms-2 text-xs text-souk-brown-deep/55 tabular-nums">
                                                ({t('buyer.orders.items_total')}{' '}
                                                {money(purchase.items_total)} +{' '}
                                                {t('buyer.orders.delivery')}{' '}
                                                {money(purchase.delivery_fee)})
                                            </span>
                                        )}
                                    </dd>
                                </dl>

                                {/* ── Every piece, from every workshop ── */}
                                <ul className="mt-5 grid gap-4 sm:grid-cols-2">
                                    {purchase.orders.flatMap((order) =>
                                        order.items.map((item) => (
                                            <li
                                                key={item.id}
                                                className="flex gap-4"
                                            >
                                                <span className="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-souk-peach">
                                                    {item.product.image && (
                                                        <img
                                                            src={
                                                                item.product
                                                                    .image
                                                            }
                                                            alt=""
                                                            loading="lazy"
                                                            className="h-full w-full object-cover"
                                                        />
                                                    )}
                                                </span>
                                                <div className="min-w-0 flex-1 text-sm">
                                                    {item.product.slug ? (
                                                        <Link
                                                            href={route(
                                                                'products.show',
                                                                item.product
                                                                    .slug,
                                                            )}
                                                            className="line-clamp-2 font-semibold text-souk-title hover:underline"
                                                        >
                                                            {item.product.name}
                                                        </Link>
                                                    ) : (
                                                        <p className="line-clamp-2 font-semibold text-souk-title">
                                                            {item.product.name}
                                                        </p>
                                                    )}
                                                    <p className="mt-1 text-souk-brown-deep/65 tabular-nums">
                                                        {t(
                                                            'buyer.orders.quantity',
                                                        )}
                                                        : {item.quantity}× ={' '}
                                                        {money(item.unit_price)}
                                                    </p>
                                                    {item.variant_label && (
                                                        <p className="text-souk-brown-deep/65">
                                                            {t(
                                                                'buyer.orders.variant',
                                                            )}
                                                            :{' '}
                                                            {item.variant_label}
                                                        </p>
                                                    )}
                                                    {order.store.store_name && (
                                                        <p className="text-souk-brown-deep/65">
                                                            {t(
                                                                'buyer.orders.workshop',
                                                            )}
                                                            :{' '}
                                                            {
                                                                order.store
                                                                    .store_name
                                                            }
                                                            {/* Only when this
                                                                shop has moved
                                                                apart from the
                                                                purchase — one
                                                                parcel can land
                                                                before another. */}
                                                            {split &&
                                                                order.status !==
                                                                    purchase.status && (
                                                                    <span
                                                                        className={`ms-2 font-semibold ${STATUS_COLOURS[order.status].text}`}
                                                                    >
                                                                        ·{' '}
                                                                        {t(
                                                                            `buyer.orders.status_${order.status}`,
                                                                        )}
                                                                    </span>
                                                                )}
                                                        </p>
                                                    )}
                                                </div>
                                            </li>
                                        )),
                                    )}
                                </ul>

                                {/* ── Follow-ups: history, and rating ── */}
                                <div className="mt-5 flex flex-wrap items-center gap-2 border-t border-souk-brown/10 pt-4">
                                    <Link
                                        href={route(
                                            'buyer.purchases.tracking',
                                            purchase.reference,
                                        )}
                                        className="inline-flex items-center gap-2 rounded-lg border border-souk-brown/20 px-3.5 py-2 text-sm font-medium text-souk-title transition-colors hover:bg-souk-peach"
                                    >
                                        <History
                                            className="h-4 w-4"
                                            strokeWidth={1.8}
                                        />
                                        {t('buyer.orders.track')}
                                    </Link>

                                    {/* Offered once something has been
                                        delivered — `can_review` is the
                                        server's rule. Filled while pieces are
                                        still waiting for stars; outlined once
                                        everything is rated, to reread them. */}
                                    {purchase.can_review && (
                                        <Link
                                            href={route(
                                                'buyer.purchases.review',
                                                purchase.reference,
                                            )}
                                            className={
                                                purchase.review_open_count > 0
                                                    ? 'inline-flex items-center gap-2 rounded-lg bg-souk-brown px-3.5 py-2 text-sm font-medium text-white transition-colors hover:bg-souk-brown-deep'
                                                    : 'inline-flex items-center gap-2 rounded-lg border border-souk-brown/20 px-3.5 py-2 text-sm font-medium text-souk-title transition-colors hover:bg-souk-peach'
                                            }
                                        >
                                            <Star
                                                className="h-4 w-4"
                                                strokeWidth={1.8}
                                            />
                                            {purchase.review_open_count > 0
                                                ? t('buyer.orders.rate')
                                                : t('buyer.orders.my_reviews')}
                                            {purchase.review_open_count > 0 && (
                                                <span className="rounded-full bg-white/20 px-1.5 text-xs tabular-nums">
                                                    {purchase.review_open_count}
                                                </span>
                                            )}
                                        </Link>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </BuyerAccountLayout>
    );
}
