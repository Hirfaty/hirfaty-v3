<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The admin products table and its download.
 *
 * One filter builder serves the table, its status-tab counts and the export,
 * so the file is always exactly what the admin is looking at — only
 * unpaginated.
 */
class AdminProductListService
{
    private const STATUS_LABELS = [
        'draft' => 'Brouillon',
        'pending_review' => 'En attente',
        'published' => 'Publié',
        'rejected' => 'Rejeté',
        'archived' => 'Archivé',
    ];

    private const VISIBILITY_LABELS = [
        'both' => 'Tous',
        'individual' => 'Particuliers',
        'professional' => 'Professionnels',
    ];

    public function __construct(
        private readonly SpreadsheetExportService $spreadsheets,
    ) {}

    /**
     * The search / category / status filters and nothing else — no eager
     * loads, no ordering — so the same builder can also be counted and
     * grouped for the status tabs.
     *
     * @param  array{search?: ?string, category_id?: int|string|null, status?: ?string}  $filters
     * @return Builder<Product>
     */
    public function query(array $filters): Builder
    {
        return Product::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('store', fn ($q) => $q->where('store_name', 'like', "%{$search}%"));
            }))
            ->when($filters['category_id'] ?? null, fn ($query, $category) => $query->where('category_id', $category))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
    }

    /**
     * Every product the filters match, newest first, as CSV or Excel.
     *
     * @param  'csv'|'xlsx'  $format
     */
    public function export(string $format, array $filters): StreamedResponse|BinaryFileResponse
    {
        return $this->spreadsheets->download(
            $format,
            'produits',
            [
                'ID',
                'Nom',
                'SKU',
                'Boutique',
                'Catégorie',
                'Statut',
                'Prix (MAD)',
                'Prix soldé (MAD)',
                'Prix pro (MAD)',
                'Prix pro soldé (MAD)',
                'Qté min. pro',
                'Stock',
                'Variantes',
                'Visible par',
                'Ventes',
                'Note moyenne',
                'Motif de refus',
                'Créé le',
                'Lien public',
            ],
            fn () => $this->query($filters)
                ->with(['store:id,store_name', 'category'])
                ->withCount('orderItems')
                ->withSum('variants', 'stock')
                ->lazyByIdDesc(500)
                ->map(fn (Product $product) => $this->row($product)),
        );
    }

    /** @return list<string|int|float> */
    private function row(Product $product): array
    {
        return [
            $product->id,
            $product->name,
            $product->sku ?? '',
            $product->store?->store_name ?? '',
            $product->category?->translated_name ?? '',
            self::STATUS_LABELS[$product->status] ?? $product->status,
            $this->money($product->base_price),
            $this->money($product->sale_price),
            $this->money($product->professional_price),
            $this->money($product->professional_sale_price),
            (int) $product->min_order_qty_professional,
            // With variants, stock is tracked per variant and the product's own
            // column stays 0 — the total is what the admin means by "stock".
            $product->has_variants ? (int) $product->variants_sum_stock : (int) $product->stock,
            $product->has_variants ? 'Oui' : 'Non',
            self::VISIBILITY_LABELS[$product->visibility] ?? (string) $product->visibility,
            (int) $product->order_items_count,
            $product->average_rating !== null ? (float) $product->average_rating : '',
            $product->rejection_reason ?? '',
            $product->created_at?->format('Y-m-d H:i') ?? '',
            // Only a published piece has a page a visitor can open.
            $product->isPublished() ? route('products.show', ['locale' => 'fr', 'slug' => $product->slug]) : '',
        ];
    }

    /** Blank for "not priced yet", which is not the same as 0. */
    private function money(?string $amount): float|string
    {
        return $amount === null ? '' : (float) $amount;
    }
}
