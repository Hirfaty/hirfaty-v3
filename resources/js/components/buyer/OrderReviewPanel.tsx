import { router } from '@inertiajs/react';
import { Check, Loader2 } from 'lucide-react';
import { useState } from 'react';
import StarRating from '@/components/buyer/StarRating';
import { useTranslation } from '@/hooks/use-translation';
import type { BuyerOrder } from '@/types/orders';

/**
 * "How did this go?" — the rating form on a delivered order.
 *
 * Two rules shape it:
 *
 *  - It rates the *pieces*, not the artisan. A shop rating used to sit at the
 *    bottom; it was asking the buyer to judge a person for a parcel, and it is
 *    gone.
 *  - A rating is final. Once sent, that piece's stars turn into a statement of
 *    what was said and the comment box closes with them — so the form only
 *    ever shows an input for something genuinely still open. The server
 *    refuses a second rating regardless (ReviewService), which is what makes
 *    this safe to be a display decision.
 *
 * Everything is optional and submitted together: a buyer who wants to rate one
 * of three pieces can, and the payload simply carries fewer entries. The rest
 * stay open for a later visit.
 */
export default function OrderReviewPanel({ order }: { order: BuyerOrder }) {
    const { t } = useTranslation();

    const items = order.items.filter((item) => item.product_id);
    const open = items.filter((item) => !item.review);
    const rated = items.filter((item) => item.review);

    const [ratings, setRatings] = useState<Record<number, number>>({});
    const [comments, setComments] = useState<Record<number, string>>({});
    const [saving, setSaving] = useState(false);

    // Reflects the last successful submit rather than the presence of saved
    // data, so the confirmation is feedback on *this* action.
    const [saved, setSaved] = useState(false);

    const chosen = Object.entries(ratings).filter(([, rating]) => rating > 0);

    const submit = () => {
        if (chosen.length === 0) {
            return;
        }

        router.post(
            route('buyer.orders.reviews.store', order.reference),
            {
                products: Object.fromEntries(
                    chosen.map(([id, rating]) => [
                        id,
                        {
                            rating,
                            comment: comments[Number(id)] || null,
                        },
                    ]),
                ),
            },
            {
                preserveScroll: true,
                onStart: () => {
                    setSaving(true);
                    setSaved(false);
                },
                // No local "now it's rated" bookkeeping: the reload brings
                // back items carrying their new `review`, which is what closes
                // the stars. One source of truth for whether a piece is done.
                onSuccess: () => setSaved(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    const starLabel = (n: number) =>
        t('buyer.reviews.star_label').replace(':count', String(n));

    return (
        <div className="mt-5 border-t border-souk-brown/12 pt-5">
            <p className="font-display text-sm text-souk-title">
                {open.length === 0
                    ? t('buyer.reviews.all_rated_heading')
                    : t('buyer.reviews.heading')}
            </p>

            <div className="mt-4 space-y-4">
                {/* Still open, in the order's own order — so a part-rated
                    delivery doesn't reshuffle its pieces under the buyer. */}
                {open.map((item) => {
                    const productId = item.product_id as number;
                    const rating = ratings[productId] ?? 0;

                    return (
                        <div key={item.id}>
                            <div className="flex items-center justify-between gap-4">
                                <span className="min-w-0 flex-1 truncate text-sm text-souk-brown-deep/85">
                                    {item.product.name}
                                </span>
                                <StarRating
                                    name={`order-${order.id}-product-${productId}`}
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
                            </div>

                            {/* Only once this piece has stars — an empty
                                comment box under an untouched row is a
                                question nobody asked yet. */}
                            {rating > 0 && (
                                <textarea
                                    value={comments[productId] ?? ''}
                                    onChange={(e) =>
                                        setComments((prev) => ({
                                            ...prev,
                                            [productId]: e.target.value,
                                        }))
                                    }
                                    rows={2}
                                    maxLength={1000}
                                    placeholder={t(
                                        'buyer.reviews.comment_placeholder',
                                    )}
                                    aria-label={`${t('buyer.reviews.comment_placeholder')} — ${item.product.name}`}
                                    className="mt-2 w-full resize-none border border-souk-brown/20 bg-souk-paper px-3 py-2 text-sm text-souk-title transition-colors placeholder:text-souk-brown-deep/40 focus:border-souk-brown/50 focus:outline-none"
                                />
                            )}
                        </div>
                    );
                })}

                {/* Closed for good. Shown rather than dropped: the buyer
                    should be able to see what they said about a piece, and a
                    row vanishing after submitting reads as the rating having
                    been lost. */}
                {rated.map((item) => (
                    <div key={item.id} className="opacity-70">
                        <div className="flex items-center justify-between gap-4">
                            <span className="min-w-0 flex-1 truncate text-sm text-souk-brown-deep/85">
                                {item.product.name}
                            </span>
                            <StarRating
                                readOnly
                                name={`order-${order.id}-product-${item.product_id}-rated`}
                                label={`${t('buyer.reviews.rated')} — ${item.product.name}`}
                                starLabel={starLabel}
                                value={item.review!.rating}
                                onChange={() => {}}
                            />
                        </div>

                        {item.review!.comment && (
                            <p className="mt-1 text-sm text-souk-brown-deep/60 italic">
                                {item.review!.comment}
                            </p>
                        )}
                    </div>
                ))}
            </div>

            {open.length > 0 && (
                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        onClick={submit}
                        disabled={chosen.length === 0 || saving}
                        className="inline-flex items-center gap-2 border border-souk-brown bg-souk-brown px-5 py-2 text-sm text-souk-cream transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {saving && <Loader2 className="h-4 w-4 animate-spin" />}
                        {t('buyer.reviews.submit')}
                    </button>

                    {/* Said before the fact, not after: it is the one thing
                        the buyer needs to know while the stars are still
                        changeable. */}
                    <span className="text-xs text-souk-brown-deep/55">
                        {t('buyer.reviews.final_notice')}
                    </span>
                </div>
            )}

            {saved && (
                <p
                    // Announced rather than only shown: the rows re-render in
                    // place, so nothing else signals that the submit worked.
                    role="status"
                    className="mt-3 inline-flex items-center gap-1.5 text-xs text-olive"
                >
                    <Check className="h-3.5 w-3.5" />
                    {t('buyer.reviews.thanks')}
                </p>
            )}
        </div>
    );
}
