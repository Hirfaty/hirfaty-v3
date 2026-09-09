import { router } from '@inertiajs/react';
import { Check, Loader2 } from 'lucide-react';
import { useState } from 'react';
import StarRating from '@/components/buyer/StarRating';
import { useTranslation } from '@/hooks/use-translation';
import type { BuyerOrder } from '@/types/orders';

/**
 * "How did this go?" — the rating form on a delivered order.
 *
 * One form for the whole order: every piece in it, then the shop, submitted
 * together. A per-product form would mean several round trips for what is a
 * single moment of feedback, and the shop rating has no natural home if the
 * pieces are each their own submission.
 *
 * Everything is optional. A buyer who only wants to rate one of three pieces
 * can, and the payload simply carries fewer entries — `ReviewService` treats
 * an absent product as "no opinion", not as a zero.
 */
export default function OrderReviewPanel({ order }: { order: BuyerOrder }) {
    const { t } = useTranslation();

    // Seeded from what the buyer already said, so re-opening a rated order
    // shows their own answers rather than a blank form implying it was never
    // submitted.
    const [productRatings, setProductRatings] = useState<
        Record<number, number>
    >(() =>
        Object.fromEntries(
            order.items
                .filter((item) => item.product_id && item.review)
                .map((item) => [
                    item.product_id as number,
                    item.review!.rating,
                ]),
        ),
    );

    const [storeRating, setStoreRating] = useState<number>(
        order.store_review?.rating ?? 0,
    );

    const [comment, setComment] = useState<string>(
        order.store_review?.comment ?? '',
    );

    const [saving, setSaving] = useState(false);

    // Reflects the last successful submit rather than the presence of saved
    // data, so the confirmation is feedback on *this* action.
    const [saved, setSaved] = useState(false);

    const alreadyRated =
        order.items.some((item) => item.review) || order.store_review !== null;

    const hasAnything =
        Object.keys(productRatings).length > 0 || storeRating > 0;

    const submit = () => {
        if (!hasAnything) {
            return;
        }

        router.post(
            route('buyer.orders.reviews.store', order.reference),
            {
                products: Object.fromEntries(
                    Object.entries(productRatings).map(([id, rating]) => [
                        id,
                        { rating },
                    ]),
                ),
                // Only sent when a shop rating was actually given: the server
                // treats `store: null` as "skipped", and posting a 0 would
                // fail validation rather than being read as no answer.
                store:
                    storeRating > 0
                        ? { rating: storeRating, comment: comment || null }
                        : null,
            },
            {
                preserveScroll: true,
                onStart: () => {
                    setSaving(true);
                    setSaved(false);
                },
                onSuccess: () => setSaved(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="mt-5 border-t border-souk-brown/12 pt-5">
            <p className="font-display text-sm text-souk-title">
                {alreadyRated
                    ? t('buyer.reviews.edit_heading')
                    : t('buyer.reviews.heading')}
            </p>

            <div className="mt-4 space-y-3">
                {order.items
                    .filter((item) => item.product_id)
                    .map((item) => (
                        <div
                            key={item.id}
                            className="flex items-center justify-between gap-4"
                        >
                            <span className="min-w-0 flex-1 truncate text-sm text-souk-brown-deep/85">
                                {item.product.name}
                            </span>
                            <StarRating
                                name={`order-${order.id}-product-${item.product_id}`}
                                label={`${t('buyer.reviews.rate_product')} — ${item.product.name}`}
                                starLabel={(n) =>
                                    t('buyer.reviews.star_label').replace(
                                        ':count',
                                        String(n),
                                    )
                                }
                                value={
                                    productRatings[item.product_id as number] ??
                                    0
                                }
                                onChange={(rating) =>
                                    setProductRatings((prev) => ({
                                        ...prev,
                                        [item.product_id as number]: rating,
                                    }))
                                }
                            />
                        </div>
                    ))}

                {order.store.store_name && (
                    <div className="flex items-center justify-between gap-4 border-t border-souk-brown/10 pt-3">
                        <span className="min-w-0 flex-1 truncate text-sm text-souk-brown-deep/85">
                            {t('buyer.reviews.rate_shop')} ·{' '}
                            {order.store.store_name}
                        </span>
                        <StarRating
                            name={`order-${order.id}-store`}
                            label={t('buyer.reviews.rate_shop')}
                            starLabel={(n) =>
                                t('buyer.reviews.star_label').replace(
                                    ':count',
                                    String(n),
                                )
                            }
                            value={storeRating}
                            onChange={setStoreRating}
                        />
                    </div>
                )}
            </div>

            {/* Only once something has been rated — an empty comment box above
                an untouched form is a question nobody asked yet. */}
            {hasAnything && (
                <textarea
                    value={comment}
                    onChange={(e) => setComment(e.target.value)}
                    rows={2}
                    maxLength={1000}
                    placeholder={t('buyer.reviews.comment_placeholder')}
                    aria-label={t('buyer.reviews.comment_placeholder')}
                    className="mt-4 w-full resize-none border border-souk-brown/20 bg-souk-paper px-3 py-2 text-sm text-souk-title transition-colors placeholder:text-souk-brown-deep/40 focus:border-souk-brown/50 focus:outline-none"
                />
            )}

            <div className="mt-4 flex items-center gap-3">
                <button
                    type="button"
                    onClick={submit}
                    disabled={!hasAnything || saving}
                    className="inline-flex items-center gap-2 border border-souk-brown bg-souk-brown px-5 py-2 text-sm text-souk-cream transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {saving && <Loader2 className="h-4 w-4 animate-spin" />}
                    {t('buyer.reviews.submit')}
                </button>

                {saved && (
                    <span
                        // Announced rather than only shown: the button stays
                        // put and the only change is this line appearing, so
                        // nothing else signals that the submit worked.
                        role="status"
                        className="inline-flex items-center gap-1.5 text-xs text-olive"
                    >
                        <Check className="h-3.5 w-3.5" />
                        {t('buyer.reviews.thanks')}
                    </span>
                )}
            </div>
        </div>
    );
}
