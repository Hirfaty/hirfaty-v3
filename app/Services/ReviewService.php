<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Product ratings for a delivered order.
 *
 * The single write path, because the two rules that make a rating trustworthy
 * are *server* rules:
 *
 *  1. You may only rate what was actually delivered to you.
 *  2. You get one shot. A rating is a first impression of a piece you have in
 *     hand, not a running opinion — once left it is final, and the form closes.
 *
 * The UI honours both (the stars lock after submitting), but that is a
 * convenience: a hand-rolled POST must fail the same way, so the enforcement
 * lives here rather than in the controller or the page.
 *
 * Shop ratings are not written here any more. `StoreReview` and its table
 * survive — nothing has been dropped, so turning them back on is UI work — but
 * for now the buyer rates the pieces, not the artisan.
 */
class ReviewService
{
    /**
     * Orders a buyer is allowed to rate: their own, and delivered.
     *
     * `delivered` specifically, not "not cancelled" — a shipped order hasn't
     * arrived yet, and the whole point is that the buyer has the piece in
     * hand before judging it.
     */
    public function canReview(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id && $order->status === 'delivered';
    }

    /**
     * The products in this order that this buyer may still rate — delivered,
     * and not already rated.
     *
     * Drives both the form (which stars are live) and the write below, so the
     * page and the server cannot disagree about what is still open.
     *
     * @return array<int, int>
     */
    public function openProductIds(User $user, Order $order): array
    {
        if (! $this->canReview($user, $order)) {
            return [];
        }

        $inOrder = $order->items()
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->unique();

        $alreadyRated = ProductReview::where('user_id', $user->id)
            ->whereIn('product_id', $inOrder)
            ->pluck('product_id');

        return $inOrder->diff($alreadyRated)->values()->all();
    }

    /**
     * Where this viewer stands with rating one product, for its public page.
     *
     * Null when there is nothing to show — a guest, or a buyer who has never
     * had this piece delivered. Otherwise their review if they left one
     * (final, so it is shown rather than re-asked), or `open` when a
     * delivered order holds it and it is still unrated.
     *
     * @return array{status: 'open'}|array{status: 'rated', rating: int, comment: string|null}|null
     */
    public function stateForProduct(?User $user, Product $product): ?array
    {
        if ($user === null) {
            return null;
        }

        $review = ProductReview::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($review) {
            return ['status' => 'rated', 'rating' => $review->rating, 'comment' => $review->comment];
        }

        return $this->deliveredOrderFor($user, $product) ? ['status' => 'open'] : null;
    }

    /**
     * Rate one product from its own page.
     *
     * Routed to a delivered order that holds the piece and handed to submit(),
     * so ownership, delivery and one-shot are enforced exactly as they are on
     * the order pages. No delivered order is the same 403 submit() gives.
     *
     * @param  array{rating: int, comment?: string|null}  $entry
     * @return int how many ratings were actually recorded (0 or 1)
     */
    public function submitForProduct(User $user, Product $product, array $entry): int
    {
        $order = $this->deliveredOrderFor($user, $product) ?? abort(403);

        return $this->submit($user, $order, [$product->id => $entry]);
    }

    /** The buyer's latest delivered order that contains this product. */
    private function deliveredOrderFor(User $user, Product $product): ?Order
    {
        return Order::query()
            ->where('buyer_id', $user->id)
            ->where('status', 'delivered')
            ->whereHas('items', fn ($items) => $items->where('product_id', $product->id))
            ->latest('id')
            ->first();
    }

    /**
     * How many delivered orders still have a piece this buyer hasn't rated.
     *
     * Feeds the prompt shown on arrival (see HandleInertiaRequests), so it runs
     * on responses for every signed-in buyer — hence one query rather than
     * loading the orders and their items to count in PHP.
     *
     * Counts *orders*, not products: the prompt says "you have deliveries to
     * rate" and sends them to the order list, so the order is the unit the
     * number has to match.
     */
    public function pendingOrderCount(User $user): int
    {
        return Order::query()
            ->where('buyer_id', $user->id)
            ->where('status', 'delivered')
            ->whereHas('items', fn ($items) => $items
                ->whereNotNull('product_id')
                ->whereNotExists(fn ($rated) => $rated
                    ->selectRaw('1')
                    ->from('product_reviews')
                    ->whereColumn('product_reviews.product_id', 'order_items.product_id')
                    ->where('product_reviews.user_id', $user->id)
                )
            )
            ->count();
    }

    /**
     * Record a buyer's ratings for one delivered order.
     *
     * `$productRatings` is keyed by product id. Anything in it that isn't open
     * — not in this order, or already rated — is dropped rather than rejected:
     * the page posts the whole form, and a stale entry is a stale payload, not
     * something to fail the whole submission over. What matters is that it
     * cannot be *written*.
     *
     * @param  array<int, array{rating: int, comment?: string|null}>  $productRatings
     * @return int how many ratings were actually recorded
     */
    public function submit(User $user, Order $order, array $productRatings): int
    {
        if (! $this->canReview($user, $order)) {
            // Mirrors a missing order rather than explaining the rule: whether
            // someone else's order exists, and what state it is in, is not
            // this buyer's business.
            abort(403);
        }

        $openProductIds = $this->openProductIds($user, $order);

        return DB::transaction(function () use ($user, $order, $productRatings, $openProductIds) {
            $written = [];

            foreach ($productRatings as $productId => $entry) {
                $productId = (int) $productId;

                if (! in_array($productId, $openProductIds, true)) {
                    continue;
                }

                /*
                 * firstOrCreate, not updateOrCreate: the unique index on
                 * (user_id, product_id) is what "one shot" is actually made
                 * of, and this is the only shape that leaves an existing row
                 * untouched if two submissions race past the check above.
                 */
                $review = ProductReview::firstOrCreate(
                    ['user_id' => $user->id, 'product_id' => $productId],
                    [
                        'order_id' => $order->id,
                        'rating' => $entry['rating'],
                        'comment' => $entry['comment'] ?? null,
                    ],
                );

                if ($review->wasRecentlyCreated) {
                    $written[] = $productId;
                }
            }

            foreach ($written as $productId) {
                $this->recomputeProductAverage($productId);
            }

            return count($written);
        });
    }

    /**
     * Record ratings for a whole purchase — the "Rate my items" page, which
     * lists every delivered piece across the purchase's workshops.
     *
     * Delegates to submit() once per delivered order, so the rules above are
     * enforced exactly as they are for a single order: submit() drops anything
     * not open in that order, which is what routes each product's rating to
     * the order it was actually delivered in.
     *
     * @param  Collection<int, Order>  $orders  the purchase, already scoped to its buyer
     * @param  array<int, array{rating: int, comment?: string|null}>  $productRatings
     * @return int how many ratings were actually recorded
     */
    public function submitPurchase(User $user, Collection $orders, array $productRatings): int
    {
        $reviewable = $orders->filter(fn (Order $order) => $this->canReview($user, $order));

        if ($reviewable->isEmpty()) {
            abort(403);
        }

        return $reviewable->sum(fn (Order $order) => $this->submit($user, $order, $productRatings));
    }

    /**
     * The rating block at the top of a product's public reviews: average,
     * count, and how many reviews landed on each star.
     *
     * The average is taken from the same grouped counts rather than from the
     * cached `products.average_rating`, so the headline figure and the bars
     * under it can never disagree.
     *
     * @return array{average: float|null, count: int, distribution: array<int, int>}
     */
    public function summaryFor(Product $product): array
    {
        $counts = ProductReview::where('product_id', $product->id)
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $total = (int) $counts->sum();
        $distribution = [];

        foreach ([5, 4, 3, 2, 1] as $star) {
            $distribution[$star] = (int) $counts->get($star, 0);
        }

        $weighted = collect($distribution)->map(fn ($count, $star) => $star * $count)->sum();

        return [
            'average' => $total > 0 ? round($weighted / $total, 1) : null,
            'count' => $total,
            'distribution' => $distribution,
        ];
    }

    /**
     * A product's reviews, newest first, for the public product page.
     *
     * Only the author's name columns are loaded — the page shows a masked
     * name and nothing else about the buyer.
     *
     * Its own page name so "load more reviews" never collides with any other
     * paginated prop on the product page.
     */
    public function paginateFor(Product $product, int $perPage = 6): LengthAwarePaginator
    {
        return ProductReview::where('product_id', $product->id)
            ->with('user:id,first_name,last_name')
            ->latest()
            ->latest('id')
            ->paginate($perPage, pageName: 'reviews_page');
    }

    /**
     * Every review of a product with its *unmasked* author, for the admin
     * product page. Unlike paginateFor() this is for Hirfaty's desk only —
     * the desk needs to know who said what (and on which order) to moderate
     * or follow up, so the buyer's identity is deliberately included here.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forAdmin(Product $product): array
    {
        return ProductReview::where('product_id', $product->id)
            ->with(['user:id,first_name,last_name,email', 'order:id,reference'])
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (ProductReview $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at?->toIso8601String(),
                'user' => $review->user ? [
                    'id' => $review->user->id,
                    'first_name' => $review->user->first_name,
                    'last_name' => $review->user->last_name,
                    'email' => $review->user->email,
                ] : null,
                'order_reference' => $review->order?->reference,
            ])
            ->all();
    }

    /**
     * Recompute a product's cached average from its reviews.
     *
     * Recomputed from the full set rather than folded incrementally into the
     * existing value: cheap at these volumes, and it self-heals if a review is
     * ever removed by hand.
     *
     * Written with a bare update() so no model events fire — `average_rating`
     * is derived data, and anything listening for a product *change* would be
     * reacting to its own consequence.
     */
    private function recomputeProductAverage(int $productId): void
    {
        $average = ProductReview::where('product_id', $productId)->avg('rating');

        Product::where('id', $productId)->update([
            'average_rating' => round((float) $average, 2),
        ]);
    }
}
