import EmptyShowcase from '@/components/buyer/EmptyShowcase';
import OrderReviewPanel from '@/components/buyer/OrderReviewPanel';
import { useCurrency } from '@/hooks/use-currency';
import { useTranslation } from '@/hooks/use-translation';
import BuyerAccountLayout from '@/layouts/buyer/account-layout';
import type { BuyerPurchase, OrderStatus } from '@/types/orders';

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

/**
 * The order history: one card per purchase.
 *
 * A basket spanning three workshops is three `Order` rows in the database —
 * that is who gets paid and who gets notified — but the buyer bought one
 * thing, and three near-identical cards read as three purchases they don't
 * remember making. So the card is the checkout, and the split shows up inside
 * it as the shop each piece came from.
 *
 * The split is not hidden, only demoted: status and tracking really are per
 * workshop (one parcel can arrive while another is still being packed), so
 * each shop keeps its own pill and its own rating form.
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
            {purchases.length === 0 ? (
                <EmptyShowcase
                    eyebrow={t('buyer.orders.empty_eyebrow')}
                    title={t('buyer.orders.empty_title')}
                    ctaLabel={t('buyer.orders.empty_cta')}
                />
            ) : (
                <ul className="space-y-5">
                    {purchases.map((purchase) => {
                        // Several workshops changes what the card has to say —
                        // whose pieces are whose, and that the pills below the
                        // header are per shop rather than repetition.
                        const split = purchase.orders.length > 1;

                        return (
                            <li
                                key={purchase.reference}
                                // A soft card rather than a hairline box,
                                // matching the address card and the empty
                                // state — the whole account area now lifts its
                                // objects off the paper instead of drawing
                                // edges around them.
                                className="rounded-2xl bg-white p-5 shadow-sm shadow-souk-brown/5 sm:p-6"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        {/* Tabular figures so a column of
                                            references lines up digit for
                                            digit — they get read by eye when
                                            matching one against an email. */}
                                        <p className="font-display text-base tracking-[0.04em] text-souk-title tabular-nums">
                                            {purchase.reference}
                                        </p>
                                        <p className="mt-1 text-sm text-souk-brown-deep/60">
                                            {split
                                                ? t(
                                                      'buyer.orders.shops_count',
                                                  ).replace(
                                                      ':count',
                                                      String(
                                                          purchase.orders
                                                              .length,
                                                      ),
                                                  )
                                                : purchase.orders[0]?.store
                                                      .store_name}
                                            {purchase.created_at && (
                                                <>
                                                    {' '}
                                                    ·{' '}
                                                    {date(purchase.created_at)}
                                                </>
                                            )}
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-3">
                                        <span
                                            className={`rounded-full px-3 py-1 text-[11px] font-semibold tracking-[0.14em] uppercase ${STATUS_STYLES[purchase.status]}`}
                                        >
                                            {t(
                                                `buyer.orders.status_${purchase.status}`,
                                            )}
                                        </span>
                                        <span className="font-display text-base text-souk-green">
                                            {money(purchase.total_amount)}
                                            TTC
                                        </span>
                                    </div>
                                </div>

                                <div className="mt-4 space-y-5">
                                    {purchase.orders.map((order) => (
                                        <div key={order.id}>
                                            {/* Named only once the basket
                                                actually spans workshops —
                                                against a single shop this
                                                would just repeat the header. */}
                                            {split && (
                                                <div className="mb-2 flex items-center justify-between gap-3">
                                                    <span className="min-w-0 truncate text-xs font-semibold tracking-[0.12em] text-souk-brown-deep/50 uppercase">
                                                        {order.store.store_name}
                                                    </span>
                                                    <span
                                                        className={`shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-semibold tracking-[0.12em] uppercase ${STATUS_STYLES[order.status]}`}
                                                    >
                                                        {t(
                                                            `buyer.orders.status_${order.status}`,
                                                        )}
                                                    </span>
                                                </div>
                                            )}

                                            <ul className="space-y-3">
                                                {order.items.map((item) => (
                                                    <li
                                                        key={item.id}
                                                        className="flex items-center gap-3"
                                                    >
                                                        <span className="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-souk-peach">
                                                            {item.product
                                                                .image && (
                                                                <img
                                                                    src={
                                                                        item
                                                                            .product
                                                                            .image
                                                                    }
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
                                                            {money(
                                                                item.subtotal,
                                                            )}{' '}
                                                            TTC
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>

                                            {/* `can_review` rather than
                                                re-checking the status here:
                                                what may be rated is the
                                                server's rule, and deriving it
                                                a second time in the page is
                                                how the two drift apart. Per
                                                shop, because a rating is of
                                                that workshop and its pieces. */}
                                            {order.can_review && (
                                                <OrderReviewPanel
                                                    order={order}
                                                />
                                            )}
                                        </div>
                                    ))}
                                </div>

                                {/* The pieces above are priced per shop, but
                                    delivery is charged once for the basket —
                                    without this line the total in the header
                                    would look larger than the sum of what is
                                    on screen. */}
                                {Number(purchase.delivery_fee) > 0 && (
                                    <dl className="mt-5 space-y-1.5 border-t border-souk-brown/12 pt-4 text-sm">
                                        <div className="flex justify-between gap-4 text-souk-brown-deep/60">
                                            <dt>
                                                {t('buyer.orders.items_total')}
                                            </dt>
                                            <dd className="tabular-nums">
                                                {money(purchase.items_total)}{' '}
                                                TTC
                                            </dd>
                                        </div>
                                        <div className="flex justify-between gap-4 text-souk-brown-deep/60">
                                            <dt>
                                                {t('buyer.orders.delivery')}
                                            </dt>
                                            <dd className="tabular-nums">
                                                {money(purchase.delivery_fee)}{' '}
                                                TTC
                                            </dd>
                                        </div>
                                        <div className="flex justify-between gap-4 pt-1 text-souk-title">
                                            <dt>{t('buyer.orders.total')}</dt>
                                            <dd className="font-display tabular-nums">
                                                {money(purchase.total_amount)}{' '}
                                                TTC
                                            </dd>
                                        </div>
                                    </dl>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </BuyerAccountLayout>
    );
}
