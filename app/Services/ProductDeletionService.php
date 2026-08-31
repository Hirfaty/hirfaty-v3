<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Hard-deletes products from the admin panel.
 *
 * `order_items.product_id` cascades on delete (see the order_items migration),
 * so hard-deleting a product that has ever been ordered would silently wipe
 * the line items off historical orders — corrupting invoices, seller
 * earnings, and order history. A product with order history must be archived
 * instead; this service refuses to delete it.
 *
 * Product media (and variant media) live in the polymorphic `media` table,
 * which has no foreign key / cascade to `products`, so both the DB rows and
 * the underlying storage files are cleaned up explicitly here before the
 * product row itself is removed.
 */
class ProductDeletionService
{
    public function canDelete(Product $product): bool
    {
        return ! $product->orderItems()->exists();
    }

    /**
     * @throws RuntimeException if the product has order history.
     */
    public function delete(Product $product): void
    {

        if (! $this->canDelete($product)) {
            throw new RuntimeException(
                "Cannot delete \"{$product->name}\" — it has order history. Archive it instead."
            );
        }

        DB::transaction(function () use ($product) {
            $this->deleteMediaFor($product);
            $product->delete();
        });
    }

    /**
     * Delete every product in $ids that has no order history; silently skip
     * the rest.
     *
     * @param  array<int, int>  $ids
     * @return array{deleted: int, blocked: array<int, string>} deleted count, plus the names of any skipped products
     */
    public function deleteMany(array $ids): array
    {
        if ($ids === []) {
            return ['deleted' => 0, 'blocked' => []];
        }

        $products = Product::whereIn('id', $ids)->withCount('orderItems')->get();

        $deletable = $products->filter(fn (Product $p) => $p->order_items_count === 0);
        $blocked = $products->filter(fn (Product $p) => $p->order_items_count > 0);

        DB::transaction(function () use ($deletable) {
            foreach ($deletable as $product) {
                $this->deleteMediaFor($product);
            }

            Product::whereIn('id', $deletable->pluck('id'))->delete();
        });

        return [
            'deleted' => $deletable->count(),
            'blocked' => $blocked->pluck('name')->all(),
        ];
    }

    /**
     * Remove every media file (the product's own gallery plus each variant's
     * photos) from storage, and their DB rows. Must run before the product
     * row is deleted — nothing cascades this relation.
     */
    private function deleteMediaFor(Product $product): void
    {
        $product->loadMissing(['variants.media', 'media']);

        foreach ($product->variants as $variant) {
            $this->deleteMediaFiles($variant->media);
        }

        $this->deleteMediaFiles($product->media);
    }

    /**
     * @param  Collection<int, Media>  $media
     */
    private function deleteMediaFiles(Collection $media): void
    {
        foreach ($media as $item) {
            try {
                Storage::disk($item->disk)->delete($item->path);
            } catch (\Throwable $e) {
                Log::warning("Could not delete media file {$item->path}: ".$e->getMessage());
            }

            $item->delete();
        }
    }
}
