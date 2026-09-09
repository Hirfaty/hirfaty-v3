<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\StoreReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ratings for a delivered order.
 *
 * The single write path for both review kinds, because the rule that makes a
 * rating trustworthy — you may only rate what was actually delivered to you —
 * is a *server* rule. The UI only offers the form on delivered orders, but that
 * is a convenience, not the enforcement: a hand-rolled POST must fail the same
 * way, so every check lives here rather than in the controller or the page.
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
     * Whether this buyer may rate this specific product at all — i.e. some
     * delivered order of theirs contained it.
     *
     * Used by the product page's "you can review this" affordance, which has
     * no single order in hand. The submit path below doesn't call this: it
     * already has the order, and re-deriving eligibility from a wider query
     * there would let an item from a *different* delivered order be smuggled
     * into this one's payload.
     */
    public function hasDeliveredPurchase(User $user, Product $product): bool
    {
        return Order::query()
            ->where('buyer_id', $user->id)
            ->where('status', 'delivered')
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();
    }

    /**
     * Record a buyer's ratings for one delivered order.
     *
     * `$productRatings` is keyed by product id. Anything in it that wasn't in
     * this order is dropped rather than rejected: the page posts the whole
     * form, and a product that has since been removed from the order (or was
     * never in it) is a stale payload, not something to fail the whole
     * submission over. What matters is that it cannot be *written*.
     *
     * The shop rating is optional so a buyer can rate only the pieces, or only
     * the shop, without the form refusing to submit.
     *
     * @param  array<int, array{rating: int, comment?: string|null}>  $productRatings
     * @param  array{rating: int, comment?: string|null}|null  $storeRating
     */
    public function submit(
        User $user,
        Order $order,
        array $productRatings,
        ?array $storeRating = null,
    ): void {
        if (! $this->canReview($user, $order)) {
            // Mirrors a missing order rather than explaining the rule: whether
            // someone else's order exists, and what state it is in, is not
            // this buyer's business.
            abort(403);
        }

        // The products actually delivered in *this* order — the only ones the
        // payload may touch, whatever it claims.
        $deliveredProductIds = $order->items()
            ->pluck('product_id')
            ->filter()
            ->all();

        $touchedProductIds = [];

        DB::transaction(function () use (
            $user, $order, $productRatings, $storeRating,
            $deliveredProductIds, &$touchedProductIds
        ) {
            foreach ($productRatings as $productId => $entry) {
                $productId = (int) $productId;

                if (! in_array($productId, $deliveredProductIds, true)) {
                    continue;
                }

                ProductReview::updateOrCreate(
                    // Matches the unique index: a repeat buyer edits their
                    // existing opinion rather than adding a second one.
                    ['user_id' => $user->id, 'product_id' => $productId],
                    [
                        'order_id' => $order->id,
                        'rating' => $entry['rating'],
                        'comment' => $entry['comment'] ?? null,
                    ],
                );

                $touchedProductIds[] = $productId;
            }

            if ($storeRating !== null && $order->store_id !== null) {
                StoreReview::updateOrCreate(
                    ['user_id' => $user->id, 'order_id' => $order->id],
                    [
                        'store_id' => $order->store_id,
                        'rating' => $storeRating['rating'],
                        'comment' => $storeRating['comment'] ?? null,
                    ],
                );
            }

            foreach (array_unique($touchedProductIds) as $productId) {
                $this->recomputeProductAverage($productId);
            }

            if ($storeRating !== null && $order->store_id !== null) {
                $this->recomputeStoreAverage($order->store_id);
            }
        });
    }

    /**
     * Recompute a product's cached average from its reviews.
     *
     * Recomputed from the full set rather than folded incrementally into the
     * existing value: an edit changes an existing row, so there is no delta to
     * apply, and a running average would drift on every correction.
     *
     * Written with `saveQuietly`-equivalent semantics via a bare update() —
     * this must not fire model events, since `average_rating` is derived data
     * and anything listening for a product *change* would be reacting to its
     * own consequence.
     */
    private function recomputeProductAverage(int $productId): void
    {
        $average = ProductReview::where('product_id', $productId)->avg('rating');

        Product::where('id', $productId)->update([
            'average_rating' => round((float) $average, 2),
        ]);
    }

    private function recomputeStoreAverage(int $storeId): void
    {
        $average = StoreReview::where('store_id', $storeId)->avg('rating');

        Store::where('id', $storeId)->update([
            'average_rating' => round((float) $average, 2),
        ]);
    }
}
