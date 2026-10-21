import { router } from '@inertiajs/react';
import { BadgeCheck, Check, Loader2, Star } from 'lucide-react';
import { useState } from 'react';
import StarRating from '@/components/buyer/StarRating';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/utils';

export type ReviewSummary = {
    /** One decimal, or null while the piece has no reviews. */
    average: number | null;
    count: number;
    /** Reviews per star, keyed 5 → 1. */
    distribution: Record<number, number>;
};

export type PublicReview = {
    id: number;
    rating: number;
    comment: string | null;
    /** Masked server-side ("A*** E."); null when the buyer has no name. */
    author: string | null;
    created_at: string | null;
};

export type ReviewsPagination = {
    current_page: number;
    has_more: boolean;
};

/**
 * The viewer's own standing on this piece (ReviewService::stateForProduct):
 * open once a delivered order holds it, rated once they have used their one
 * shot. Null for everyone else — the block is simply not drawn.
 */
export type MyReview =
    | { status: 'open' }
    | { status: 'rated'; rating: number; comment: string | null }
    | null;

const LOCALE_TAGS: Record<string, string> = {
    fr: 'fr-MA',
    ar: 'ar-MA',
    en: 'en-MA',
};

/**
 * A read-only row of five stars that can show a fractional value (4.3).
 *
 * Each star is an outline with a filled copy clipped over it from the
 * inline-start edge, so a partial star fills right-to-left in Arabic just as
 * the row itself runs right-to-left.
 */
export function RatingStars({
    value,
    label,
    size = 'h-4 w-4',
}: {
    value: number;
    /** Accessible reading of the whole row, e.g. "4.3 out of 5 stars". */
    label: string;
    size?: string;
}) {
    return (
        <span
            role="img"
            aria-label={label}
            className="inline-flex items-center gap-0.5"
        >
            {[1, 2, 3, 4, 5].map((star) => {
                const fill = Math.min(1, Math.max(0, value - (star - 1)));

                return (
                    <span key={star} className="relative inline-flex">
                        <Star
                            className={`${size} fill-transparent text-souk-brown/30`}
                            strokeWidth={1.5}
                        />
                        {fill > 0 && (
                            <span
                                className="absolute inset-y-0 start-0 overflow-hidden"
                                style={{ width: `${fill * 100}%` }}
                            >
                                <Star
                                    className={`${size} fill-terracotta text-terracotta`}
                                    strokeWidth={1.5}
                                />
                            </span>
                        )}
                    </span>
                );
            })}
        </span>
    );
}

export function reviewCountLabel(t: (k: string) => string, count: number) {
    return t(
        count === 1
            ? 'buyer.detail.reviews_count_one'
            : 'buyer.detail.reviews_count',
    ).replace(':n', String(count));
}

/**
 * "You received this piece": the buyer's rating form on the product page, or
 * — since a rating is final — what they said, once said.
 *
 * Posts one product's rating; the server finds the delivered order it belongs
 * to. The reload that follows brings `myReview` back as rated, which is what
 * closes the form, along with the refreshed summary and list.
 */
function MyReviewCard({
    productId,
    productName,
    myReview,
}: {
    productId: number;
    productName: string;
    myReview: NonNullable<MyReview>;
}) {
    const { t } = useTranslation();
    const [rating, setRating] = useState(0);
    const [comment, setComment] = useState('');
    const [saving, setSaving] = useState(false);

    const starLabel = (n: number) =>
        t('buyer.reviews.star_label').replace(':count', String(n));

    const submit = () => {
        if (rating === 0) {
            return;
        }

        router.post(
            route('buyer.products.review.store', productId),
            { rating, comment: comment.trim() || null },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    if (myReview.status === 'rated') {
        return (
            <div className="mt-6 rounded-2xl border border-souk-brown/15 bg-souk-peach/40 px-5 py-4">
                <p className="text-sm font-medium text-souk-title">
                    {t('buyer.reviews.all_rated_heading')}
                </p>
                <div className="mt-2 flex items-center gap-2">
                    <StarRating
                        readOnly
                        name={`my-review-${productId}`}
                        label={`${t('buyer.reviews.rated')} — ${productName}`}
                        starLabel={starLabel}
                        value={myReview.rating}
                        onChange={() => {}}
                    />
                    <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-700">
                        <Check className="h-3.5 w-3.5" />
                        {t('buyer.reviews.rated')}
                    </span>
                </div>
                {myReview.comment && (
                    <p className="mt-2 text-sm whitespace-pre-line text-souk-brown-deep/75">
                        {myReview.comment}
                    </p>
                )}
            </div>
        );
    }

    return (
        <div className="mt-6 rounded-2xl border border-souk-brown/15 bg-white px-5 py-5 shadow-sm shadow-souk-brown/5">
            <p className="font-display text-lg text-souk-title">
                {t('buyer.detail.my_review_title')}
            </p>
            <p className="mt-1 text-sm text-souk-brown-deep/65">
                {t('buyer.detail.my_review_intro')}
            </p>

            <div className="mt-4">
                <StarRating
                    name={`rate-${productId}`}
                    label={`${t('buyer.reviews.rate_product')} — ${productName}`}
                    starLabel={starLabel}
                    value={rating}
                    onChange={setRating}
                />
            </div>
            <textarea
                value={comment}
                onChange={(e) => setComment(e.target.value)}
                rows={3}
                maxLength={1000}
                placeholder={t('buyer.rate.comment_placeholder')}
                aria-label={`${t('buyer.rate.comment_placeholder')} — ${productName}`}
                className="mt-3 w-full resize-y rounded-lg border border-souk-brown/20 bg-souk-paper px-3 py-2 text-sm text-souk-title transition-colors placeholder:text-souk-brown-deep/40 focus:border-souk-brown/50 focus:outline-none"
            />

            <div className="mt-3 flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    onClick={submit}
                    disabled={rating === 0 || saving}
                    className="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-souk-brown px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {saving && <Loader2 className="h-4 w-4 animate-spin" />}
                    {t('buyer.reviews.submit')}
                </button>
                <span className="text-xs text-souk-brown-deep/55">
                    {t('buyer.reviews.final_notice')}
                </span>
            </div>
        </div>
    );
}

/**
 * The public reviews block on a product page: an average with its star
 * breakdown, then the reviews themselves, newest first.
 *
 * Every review here comes from a delivered order (ReviewService only lets a
 * buyer rate what reached them), which is why each one carries the
 * "verified purchase" mark unconditionally.
 */
export default function ProductReviews({
    summary,
    reviews,
    pagination,
    productId,
    productName,
    myReview = null,
}: {
    summary: ReviewSummary;
    reviews: PublicReview[];
    pagination: ReviewsPagination;
    productId: number;
    productName: string;
    myReview?: MyReview;
}) {
    const { t, locale } = useTranslation();
    const [loadingMore, setLoadingMore] = useState(false);
    const starsLabel = (n: number | string) =>
        t('buyer.detail.reviews_stars_label').replace(':n', String(n));

    const loadMore = () => {
        router.reload({
            data: { reviews_page: pagination.current_page + 1 },
            only: ['reviews', 'reviewsPagination'],
            onStart: () => setLoadingMore(true),
            onFinish: () => setLoadingMore(false),
        });
    };

    return (
        <section
            id="reviews"
            className="container scroll-mt-24 border-t border-souk-brown/10 py-14"
        >
            <h2 className="font-display text-2xl text-souk-title">
                {t('buyer.detail.reviews_heading')}
            </h2>

            {myReview && (
                <MyReviewCard
                    productId={productId}
                    productName={productName}
                    myReview={myReview}
                />
            )}

            {summary.count === 0 ? (
                <div className="mt-6 rounded-2xl border border-dashed border-souk-brown/20 px-6 py-10 text-center">
                    <RatingStars value={0} label={starsLabel(0)} />
                    <p className="mt-3 text-sm text-souk-brown-deep">
                        {t('buyer.detail.reviews_empty')}
                    </p>
                    <p className="mt-1 text-[13px] text-souk-brown-deep/60">
                        {t('buyer.detail.reviews_empty_hint')}
                    </p>
                </div>
            ) : (
                <div className="mt-8 grid grid-cols-1 gap-10 lg:grid-cols-[280px_1fr] lg:gap-14">
                    {/* ── Summary ── */}
                    <div className="lg:sticky lg:top-24 lg:self-start">
                        <div className="flex items-end gap-3">
                            <p className="font-display text-5xl leading-none text-souk-title">
                                {summary.average?.toFixed(1)}
                            </p>
                            <p className="pb-1 text-sm text-souk-brown-deep/60">
                                {t('buyer.detail.reviews_out_of')}
                            </p>
                        </div>
                        <div className="mt-3">
                            <RatingStars
                                value={summary.average ?? 0}
                                label={starsLabel(summary.average ?? 0)}
                                size="h-5 w-5"
                            />
                        </div>
                        <p className="mt-2 text-[13px] text-souk-brown-deep/70">
                            {reviewCountLabel(t, summary.count)}
                        </p>

                        <ul className="mt-6 space-y-2">
                            {[5, 4, 3, 2, 1].map((star) => {
                                const n = summary.distribution[star] ?? 0;
                                const pct =
                                    summary.count > 0
                                        ? (n / summary.count) * 100
                                        : 0;

                                return (
                                    <li
                                        key={star}
                                        className="flex items-center gap-3 text-xs text-souk-brown-deep/70"
                                        aria-label={`${t(
                                            'buyer.detail.reviews_star_row',
                                        ).replace(':n', String(star))} : ${n}`}
                                    >
                                        <span className="flex w-7 shrink-0 items-center gap-1">
                                            {star}
                                            <Star
                                                className="h-3 w-3 fill-terracotta text-terracotta"
                                                strokeWidth={1.5}
                                            />
                                        </span>
                                        <span className="h-1.5 flex-1 overflow-hidden rounded-full bg-souk-brown/10">
                                            <span
                                                className="block h-full rounded-full bg-terracotta"
                                                style={{ width: `${pct}%` }}
                                            />
                                        </span>
                                        <span className="w-6 shrink-0 text-end tabular-nums">
                                            {n}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>

                    {/* ── Reviews ── */}
                    <div>
                        <ul className="divide-y divide-souk-brown/10">
                            {reviews.map((review) => (
                                <li key={review.id} className="py-6 first:pt-0">
                                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                                        <RatingStars
                                            value={review.rating}
                                            label={starsLabel(review.rating)}
                                        />
                                        {review.created_at && (
                                            <p className="text-xs text-souk-brown-deep/55">
                                                {formatDate(
                                                    review.created_at,
                                                    LOCALE_TAGS[
                                                        locale as string
                                                    ] ?? 'fr-MA',
                                                )}
                                            </p>
                                        )}
                                    </div>

                                    <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <p className="text-sm font-medium text-souk-title">
                                            {review.author ??
                                                t(
                                                    'buyer.detail.reviews_anonymous',
                                                )}
                                        </p>
                                        <span className="inline-flex items-center gap-1 text-[11px] text-olive">
                                            <BadgeCheck
                                                className="h-3.5 w-3.5"
                                                strokeWidth={1.6}
                                            />
                                            {t('buyer.detail.reviews_verified')}
                                        </span>
                                    </div>

                                    {review.comment && (
                                        <p className="mt-3 text-[14px] leading-relaxed whitespace-pre-line text-souk-brown-deep/85">
                                            {review.comment}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>

                        {pagination.has_more && (
                            <button
                                type="button"
                                onClick={loadMore}
                                disabled={loadingMore}
                                className="mt-4 inline-flex items-center gap-2 rounded-xl border border-souk-brown/25 px-5 py-2.5 text-sm font-medium text-souk-brown-deep transition-colors hover:border-souk-brown hover:text-souk-brown disabled:opacity-60"
                            >
                                {loadingMore && (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                )}
                                {t('buyer.detail.reviews_more')}
                            </button>
                        )}
                    </div>
                </div>
            )}
        </section>
    );
}
