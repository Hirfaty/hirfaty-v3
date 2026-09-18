import EmptyShowcase from '@/components/buyer/EmptyShowcase';
import OrderReviewPanel from '@/components/buyer/OrderReviewPanel';
import { useTranslation } from '@/hooks/use-translation';
import BuyerAccountLayout from '@/layouts/buyer/account-layout';
import type { BuyerOrder, OrderStatus } from '@/types/orders';

/**
 * Status → the palette its pill wears. Kept as data rather than a chain of
 * ternaries in the markup so a new status is one line here, and so the case
 * that is genuinely different — cancelled, a dead end rather than a step — is
 * visibly so rather than buried in a condition.
 *
 * Filled tints rather than outlines, so the pills stop adding five more edges
 * to a page that was mostly edges. `delivered` keeps olive rather than taking
 * the new brand green: green is already carrying the account accent and the
 * order total on this same row, and a third green in one line would stop
 * meaning anything.
 */
const STATUS_STYLES: Record<OrderStatus, string> = {
    pending: 'bg-souk-brown/10 text-souk-brown-deep/75',
    confirmed: 'bg-souk-brown/15 text-souk-brown-deep',
    shipped: 'bg-terracotta/15 text-terracotta-deep',
    delivered: 'bg-olive/15 text-olive',
    cancelled: 'bg-souk-brown/5 text-souk-brown-deep/45',
};

const LOCALE_TAGS: Record<string, string> = {
    fr: 'fr-MA',
    ar: 'ar-MA',
    en: 'en-MA',
};

export default function BuyerOrders({ orders }: { orders: BuyerOrder[] }) {
    const { t, locale } = useTranslation();
    const tag = LOCALE_TAGS[locale as string] ?? 'fr-MA';

    const money = (value: number | string) =>
        new Intl.NumberFormat(tag, { maximumFractionDigits: 0 }).format(
            Number(value),
        );

    const date = (value: string | null) =>
        value
            ? new Intl.DateTimeFormat(tag, {
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              }).format(new Date(value))
            : null;

    return (
        <BuyerAccountLayout
            active="orders"
            title={t('buyer.account.nav_orders')}
            description={t('buyer.account.orders_intro')}
        >
            {orders.length === 0 ? (
                <EmptyShowcase
                    eyebrow={t('buyer.orders.empty_eyebrow')}
                    title={t('buyer.orders.empty_title')}
                    ctaLabel={t('buyer.orders.empty_cta')}
                />
            ) : (
                <ul className="space-y-5">
                    {orders.map((order) => (
                        <li
                            key={order.id}
                            // A soft card rather than a hairline box, matching
                            // the address card and the empty state — the whole
                            // account area now lifts its objects off the paper
                            // instead of drawing edges around them.
                            className="rounded-2xl bg-white p-5 shadow-sm shadow-souk-brown/5 sm:p-6"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    {/* Tabular figures so a column of
                                        references lines up digit for digit —
                                        they get read by eye when matching one
                                        against an email. */}
                                    <p className="font-display text-base tracking-[0.04em] text-souk-title tabular-nums">
                                        {order.reference}
                                    </p>
                                    <p className="mt-1 text-sm text-souk-brown-deep/60">
                                        {order.store.store_name}
                                        {order.created_at && (
                                            <> · {date(order.created_at)}</>
                                        )}
                                    </p>
                                </div>

                                <div className="flex items-center gap-3">
                                    <span
                                        className={`rounded-full px-3 py-1 text-[11px] font-semibold tracking-[0.14em] uppercase ${STATUS_STYLES[order.status]}`}
                                    >
                                        {t(
                                            `buyer.orders.status_${order.status}`,
                                        )}
                                    </span>
                                    <span className="font-display text-base text-souk-green">
                                        {money(order.total_amount)} MAD TTC
                                    </span>
                                </div>
                            </div>

                            <ul className="mt-4 space-y-3">
                                {order.items.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex items-center gap-3"
                                    >
                                        <span className="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-souk-peach">
                                            {item.product.image && (
                                                <img
                                                    src={item.product.image}
                                                    alt=""
                                                    loading="lazy"
                                                    className="h-full w-full object-cover"
                                                />
                                            )}
                                        </span>
                                        <span className="min-w-0 flex-1 truncate text-base text-souk-brown-deep/85">
                                            {item.product.name}
                                        </span>
                                        <span className="shrink-0 text-sm text-souk-brown-deep/55">
                                            ×{item.quantity}
                                        </span>
                                        <span className="shrink-0 text-sm text-souk-brown-deep/70 tabular-nums">
                                            {money(item.subtotal)} MAD TTC
                                        </span>
                                    </li>
                                ))}
                            </ul>

                            {/* `can_review` rather than re-checking the status
                                here: what may be rated is the server's rule,
                                and deriving it a second time in the page is
                                how the two drift apart. */}
                            {order.can_review && (
                                <OrderReviewPanel order={order} />
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </BuyerAccountLayout>
    );
}
