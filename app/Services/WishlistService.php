<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;

/**
 * Which of a set of products a buyer has already wishlisted.
 *
 * Shared by every page that renders a grid of `CollectionCard`s — the
 * marketplace listing, new arrivals, and a shop's own page — so the same
 * "scoped to this page, not the buyer's whole list" rule can't drift between
 * them. See `idsFor()` for why it is scoped.
 */
class WishlistService
{
    /**
     * Ids, among the given products, already on the buyer's wishlist.
     *
     * Scoped to the page just rendered rather than the buyer's entire
     * wishlist, so a long-standing list doesn't grow the payload of every
     * visit. Returns empty for a guest or a non-buyer role, both of whom the
     * `products.wishlist` route (behind `role:buyer`) would reject anyway.
     *
     * @param  iterable<Product>  $products
     * @return array<int, int>
     */
    public function idsFor(?User $user, iterable $products): array
    {
        if (! $user?->isBuyer()) {
            return [];
        }

        $productIds = [];
        foreach ($products as $product) {
            $productIds[] = $product->id;
        }

        if ($productIds === []) {
            return [];
        }

        return Wishlist::where('user_id', $user->id)
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->all();
    }
}
