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
    public function __construct(
        private ReviewService $reviews,
        private PriceModeService $priceMode,
    ) {}

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
        // The rows under the piece list what the visitor is browsing in, like
        // every other grid — no trade-only piece in individual mode.
        $audience = $this->priceMode->audience($request);

        return [
            'product' => new BuyerProductResource($product),
            'moreFromStore' => BuyerProductResource::collection($this->moreFromStore($product, $audience)),
            'relatedProducts' => BuyerProductResource::collection($this->relatedProducts($product, $audience)),
            'proOffer' => $this->proOffer($product, $request),
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
     * What this piece would cost on the pro list, for a visitor shopping on
     * personal prices — the page sets the two side by side so a business
     * buyer sees what switching to "Pro" would get them, without switching
     * first.
     *
     * Null when there is nothing to show: already in pro mode (the price
     * block quotes it), a crawler (the landing page must read as the retail
     * figure the Merchant feed lists), a piece not sold to the trade, or one
     * with no trade rate below its public price — product or any variant.
     *
     * @return array{price: float, retail: float, min_qty: int, variants: array<int, array{price: float, retail: float}>}|null
     */
    public function proOffer(Product $product, Request $request): ?array
    {
        if ($this->priceMode->isCrawler($request)
            || $this->priceMode->audience($request) === Product::AUDIENCE_PROFESSIONAL
            || ! $product->isVisibleTo(Product::AUDIENCE_PROFESSIONAL)) {
            return null;
        }

        $variants = [];

        foreach ($product->variants ?? [] as $variant) {
            $pro = $variant->priceFor(Product::AUDIENCE_PROFESSIONAL);
            $retail = $variant->priceFor(Product::AUDIENCE_INDIVIDUAL);

            if ($pro !== null && $retail !== null && $pro < $retail) {
                $variants[$variant->id] = ['price' => $pro, 'retail' => $retail];
            }
        }

        $pro = $product->priceFor(Product::AUDIENCE_PROFESSIONAL);
        $retail = $product->priceFor(Product::AUDIENCE_INDIVIDUAL);
        $productHasRate = $pro !== null && $retail !== null && $pro < $retail;

        if (! $productHasRate && $variants === []) {
            return null;
        }

        return [
            // The product's own pair; a selected variant's, when it has one,
            // replaces it on the page.
            'price' => $productHasRate ? $pro : min(array_column($variants, 'price')),
            'retail' => $productHasRate ? $retail : $variants[array_key_first($variants)]['retail'],
            'min_qty' => $product->minOrderQtyFor(Product::AUDIENCE_PROFESSIONAL),
            'variants' => $variants,
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
            $issues[] = 'Réservé aux professionnels — visible seulement avec l’affichage « prix pro ».';
        }

        return $issues;
    }

    /** @return Collection<int, Product> */
    private function moreFromStore(Product $product, string $audience): Collection
    {
        return Product::storefront()
            ->whereIn('visibility', ['both', $audience])
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
    private function relatedProducts(Product $product, string $audience): Collection
    {
        return Product::storefront()
            ->whereIn('visibility', ['both', $audience])
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
