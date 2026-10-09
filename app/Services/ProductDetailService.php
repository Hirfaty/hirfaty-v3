<?php

namespace App\Services;

use App\Http\Resources\BuyerProductResource;
use App\Http\Resources\ProductReviewResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The props behind the public product page (`buyer/products/show`).
 *
 * Shared by the storefront and the admin preview, so the preview is the real
 * page rather than an imitation of it: whatever the buyer would see, the
 * admin sees — with the one difference that the preview does not care whether
 * the product is published, priced, or sold by a shop that is open.
 */
class ProductDetailService
{
    public function __construct(private ReviewService $reviews) {}

    /**
     * Everything the page renders, for `with()` / `load()`.
     *
     * @return array<int|string, mixed>
     */
    public function relations(): array
    {
        return [
            'store.city',
            'store.user',
            'category',
            'images',
            'videos',
            // Only sellable variants reach the selector — an inactive one
            // would render as a pickable option that the cart then refuses.
            'variants' => fn ($q) => $q->where('is_active', true)->orderBy('position'),
            'variants.options',
            'variants.media',
        ];
    }

    /**
     * The page's props, minus the per-viewer ones (`inWishlist`, `inCart`)
     * that only mean something on the storefront.
     *
     * @return array<string, mixed>
     */
    public function props(Product $product, Request $request): array
    {
        $reviews = $this->reviews->paginateFor($product);

        return [
            'product' => new BuyerProductResource($product),
            'moreFromStore' => BuyerProductResource::collection($this->moreFromStore($product)),
            'relatedProducts' => BuyerProductResource::collection($this->relatedProducts($product)),
            'reviewSummary' => $this->reviews->summaryFor($product),
            // Merged so "more reviews" appends a page instead of replacing
            // the list — same contract as the listing's `products`.
            'reviews' => Inertia::merge(
                ProductReviewResource::collection($reviews->items())->resolve($request)
            ),
            'reviewsPagination' => [
                'current_page' => $reviews->currentPage(),
                'has_more' => $reviews->hasMorePages(),
            ],
        ];
    }

    /**
     * Why this product is not on the storefront right now, in the admin's
     * language — one line per Product::scopeStorefront() condition it fails.
     * Empty when the public page would show it.
     *
     * @return list<string>
     */
    public function previewIssues(Product $product): array
    {
        $issues = [];

        if ($product->status !== 'published') {
            $issues[] = match ($product->status) {
                'pending_review' => 'En attente de validation — le produit n’est pas encore publié.',
                'rejected' => 'Refusé — le produit n’est pas publié.',
                'archived' => 'Archivé — le produit est retiré du site.',
                default => "Statut « {$product->status} » — le produit n’est pas publié.",
            };
        }

        if (! $product->isPriced()) {
            $issues[] = 'Aucun prix — le produit reste caché tant qu’il n’est pas tarifé.';
        }

        $store = $product->store;

        if ($store?->status !== 'active') {
            $issues[] = 'La boutique n’est pas active — ses produits sont masqués.';
        }

        if ($store?->user?->is_banned) {
            $issues[] = 'Le vendeur est bloqué — ses produits sont masqués.';
        }

        if ($product->visibility === Product::AUDIENCE_PROFESSIONAL) {
            $issues[] = 'Réservé aux acheteurs professionnels — les particuliers ne le voient pas.';
        }

        return $issues;
    }

    /** @return Collection<int, Product> */
    private function moreFromStore(Product $product): Collection
    {
        return Product::storefront()
            ->where('store_id', $product->store_id)
            ->where('id', '!=', $product->id)
            ->with(['images', 'variants.media'])
            ->latest()
            ->limit(4)
            ->get();
    }

    /**
     * Fallback row for the bottom of the detail page: same category, different
     * shop. A store with a single product would otherwise end the page on an
     * empty "more from this shop" strip.
     *
     * @return Collection<int, Product>
     */
    private function relatedProducts(Product $product): Collection
    {
        return Product::storefront()
            ->where('id', '!=', $product->id)
            ->where('store_id', '!=', $product->store_id)
            ->when(
                $product->category_id,
                fn ($q) => $q->where('category_id', $product->category_id),
            )
            ->with(['images', 'store', 'variants.media'])
            ->latest()
            ->limit(4)
            ->get();
    }
}
