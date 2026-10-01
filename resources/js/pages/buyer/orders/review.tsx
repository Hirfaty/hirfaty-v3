import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, Clock, Loader2 } from 'lucide-react';
import { useState } from 'react';
import StarRating from '@/components/buyer/StarRating';
import { useTranslation } from '@/hooks/use-translation';
import BuyerAccountLayout from '@/layouts/buyer/account-layout';
import type { BuyerOrderItem, BuyerPurchase } from '@/types/orders';

type Row = { item: BuyerOrderItem; store: string | null; delivered: boolean };

/**
 * "Rate my items": every piece in one purchase, each with its own stars and
 * comment.
 *
 * A piece is open when its workshop has delivered and the buyer hasn't rated
 * that product before — the same rule ReviewService enforces on submit, read
 * here from `can_review` and each item's `review` rather than re-derived.
 * Pieces still on their way are listed but closed, so the buyer can see the
 * rest will come; rated ones show what was said, since a rating is final.
 *
 * One submit for the page. Only rows with stars are sent; the rest stay open
 * for a later visit.
 */
export default function RateItems({ purchase }: { purchase: BuyerPurchase }) {
    const { t } = useTranslation();

    /*
     * One row per product, not per order line: a product rates once however
     * many workshops or lines it came through, and it is open as soon as any
     * of them has delivered.
     */
    const byProduct = new Map<number, Row>();

    for (const order of purchase.orders) {
        for (const item of order.items) {
            if (item.product_id === null) {
                continue;
            }

            const existing = byProduct.get(item.product_id);

            if (existing) {
                existing.delivered ||= order.can_review;
                continue;
            }

            byProduct.set(item.product_id, {
                item,
                store: order.store.store_name,
                delivered: order.can_review,
            });
        }
    }

    const rows = [...byProduct.values()];

    const [ratings, setRatings] = useState<Record<number, number>>({});
    const [comments, setComments] = useState<Record<number, string>>({});
    const [saving, setSaving] = useState(false);

    const chosen = Object.entries(ratings).filter(([, rating]) => rating > 0);
    const open = rows.filter((row) => row.delivered && !row.item.review);

    const starLabel = (n: number) =>
        t('buyer.reviews.star_label').replace(':count', String(n));

    const submit = () => {
        if (chosen.length === 0) {
            return;
        }

        router.post(
            route('buyer.purchases.review.store', purchase.reference),
            {
                products: Object.fromEntries(
                    chosen.map(([id, rating]) => [
                        id,
                        { rating, comment: comments[Number(id)] || null },
                    ]),
                ),
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                // The reload brings items back with their new `review`, which
                // is what closes them — clear the drafts that became reviews.
                onSuccess: () => {
                    setRatings({});
                    setComments({});
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <BuyerAccountLayout
            active="orders"
            title={t('buyer.rate.title')}
            description={t('buyer.rate.intro')}
        >
            <Link
                href={route('buyer.account.orders.index')}
                className="mb-5 inline-flex items-center gap-2 text-sm text-souk-brown-deep/60 transition-colors hover:text-souk-title"
            >
                <ArrowLeft
                    className="h-4 w-4 rtl:rotate-180"
                    strokeWidth={1.6}
                />
                {t('buyer.orders.back')}
            </Link>

            <section className="rounded-2xl bg-white p-5 shadow-sm shadow-souk-brown/5 sm:p-6">
                <h2 className="font-display text-lg text-souk-title">
                    {t('buyer.orders.number').replace(':reference', '')}
                    <bdi dir="ltr">{purchase.reference}</bdi>
                </h2>

                {open.length === 0 && rows.some((row) => row.item.review) && (
                    <p
                        role="status"
                        className="mt-3 inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3.5 py-2 text-sm text-emerald-700"
                    >
                        <Check className="h-4 w-4" />
                        {t('buyer.rate.all_done')}
                    </p>
                )}

                <ul className="mt-5 divide-y divide-souk-brown/10">
                    {rows.map(({ item, store, delivered }) => {
                        const productId = item.product_id as number;
                        const rating = ratings[productId] ?? 0;
                        const review = item.review;

                        return (
                            <li
                                key={productId}
                                className="flex gap-4 py-5 first:pt-0 last:pb-0"
                            >
                                <span className="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-souk-peach">
                                    {item.product.image && (
                                        <img
                                            src={item.product.image}
                                            alt=""
                                            loading="lazy"
                                            className="h-full w-full object-cover"
                                        />
                                    )}
                                </span>

                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold text-souk-title">
                                        {item.product.name}
                                    </p>
                                    <p className="text-sm text-souk-brown-deep/60">
                                        {[item.variant_label, store]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>

                                    {review ? (
                                        // Final: what was said, not an input.
                                        <div className="mt-3">
                                            <div className="flex items-center gap-2">
                                                <StarRating
                                                    readOnly
                                                    name={`rated-${productId}`}
                                                    label={`${t('buyer.reviews.rated')} — ${item.product.name}`}
                                                    starLabel={starLabel}
                                                    value={review.rating}
                                                    onChange={() => {}}
                                                />
                                                <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-700">
                                                    <Check className="h-3.5 w-3.5" />
                                                    {t('buyer.reviews.rated')}
                                                </span>
                                            </div>
                                            {review.comment && (
                                                <p className="mt-2 rounded-lg bg-souk-peach/60 px-3 py-2 text-sm text-souk-brown-deep/75">
                                                    {review.comment}
                                                </p>
                                            )}
                                        </div>
                                    ) : delivered ? (
                                        <div className="mt-3">
                                            <StarRating
                                                name={`rate-${productId}`}
                                                label={`${t('buyer.reviews.rate_product')} — ${item.product.name}`}
                                                starLabel={starLabel}
                                                value={rating}
                                                onChange={(next) =>
                                                    setRatings((prev) => ({
                                                        ...prev,
                                                        [productId]: next,
                                                    }))
                                                }
                                            />
                                            <textarea
                                                value={
                                                    comments[productId] ?? ''
                                                }
                                                onChange={(e) =>
                                                    setComments((prev) => ({
                                                        ...prev,
                                                        [productId]:
                                                            e.target.value,
                                                    }))
                                                }
                                                rows={3}
                                                maxLength={1000}
                                                placeholder={t(
                                                    'buyer.rate.comment_placeholder',
                                                )}
                                                aria-label={`${t('buyer.rate.comment_placeholder')} — ${item.product.name}`}
                                                className="mt-3 w-full resize-y rounded-lg border border-souk-brown/20 bg-souk-paper px-3 py-2 text-sm text-souk-title transition-colors placeholder:text-souk-brown-deep/40 focus:border-souk-brown/50 focus:outline-none"
                                            />
                                        </div>
                                    ) : (
                                        <p className="mt-3 inline-flex items-center gap-1.5 text-sm text-souk-brown-deep/55">
                                            <Clock className="h-4 w-4" />
                                            {t('buyer.rate.not_delivered')}
                                        </p>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ul>

                {open.length > 0 && (
                    <div className="mt-6 flex flex-wrap items-center gap-3 border-t border-souk-brown/10 pt-5">
                        <button
                            type="button"
                            onClick={submit}
                            disabled={chosen.length === 0 || saving}
                            className="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-souk-brown px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            {saving && (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            )}
                            {t('buyer.rate.submit')}
                        </button>
                        {/* Said before the fact: the one thing the buyer
                            needs to know while stars can still change. */}
                        <span className="text-xs text-souk-brown-deep/55">
                            {t('buyer.reviews.final_notice')}
                        </span>
                    </div>
                )}
            </section>
        </BuyerAccountLayout>
    );
}
