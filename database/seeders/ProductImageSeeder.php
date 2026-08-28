<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Attaches local test images to every product that has none.
 *
 * Source images live in database/seeders/images/products and are named
 * <category-prefix>-<n>.<ext> (pottery-1.jpg, leather-2.jpg, …). They are
 * copied into the public disk under products/{id}/ with hashed filenames and
 * registered as Media rows, exactly like a real seller upload would.
 *
 * Idempotent: a product that already owns media is left untouched.
 */
class ProductImageSeeder extends Seeder
{
    /** How many images each seeded gallery gets. */
    public const IMAGES_PER_PRODUCT = 3;

    /** Category slug => filename prefix inside the source folder. */
    public const CATEGORY_IMAGES = [
        'pottery-ceramics' => 'pottery',
        'leather-goods' => 'leather',
        'textiles-weaving' => 'textile',
        'jewelry-accessories' => 'jewelry',
        'wood-carpentry' => 'wood',
        'painting-art' => 'art',
        // 'spices-food'      => 'spices',    ← no source images downloaded yet
        // 'cosmetics-argan'  => 'cosmetics', ← no source images downloaded yet
    ];

    public function run(): void
    {
        $sourceDir = database_path('seeders/images/products');

        if (! File::isDirectory($sourceDir)) {
            $this->warn("Source folder not found: {$sourceDir} — nothing to seed.");

            return;
        }

        $disk = config('filesystems.default');
        $pools = $this->buildPools($sourceDir);

        if ($pools === []) {
            $this->warn("No source images found in {$sourceDir} — nothing to seed.");

            return;
        }

        $products = Product::with('category')
            ->doesntHave('media')
            ->orderBy('id')
            ->get();

        if ($products->isEmpty()) {
            $this->info('Every product already has media — nothing to do.');

            return;
        }

        $attached = 0;
        $skipped = [];

        // Group by category so each product in a category gets a different
        // rotation of the same pool (different thumbnail per card).
        foreach ($products->groupBy('category_id') as $group) {
            $slug = $group->first()->category?->slug;
            $pool = $pools[$slug] ?? null;

            if (! $pool) {
                $skipped[$slug ?? 'uncategorised'] = $group->count();

                continue;
            }

            foreach ($group->values() as $index => $product) {
                $this->attachImages($product, $pool, $index, $disk);
                $attached++;
            }
        }

        $this->info("✅ Attached images to {$attached} product(s).");

        foreach ($skipped as $slug => $count) {
            $this->warn("   skipped {$count} product(s) in \"{$slug}\" — no source images for that category.");
        }
    }

    /**
     * Copy a slice of the pool onto the disk and register the Media rows.
     *
     * @param  list<string>  $pool  absolute paths to source images
     */
    private function attachImages(Product $product, array $pool, int $rotation, string $disk): void
    {
        $count = min(self::IMAGES_PER_PRODUCT, count($pool));

        for ($i = 0; $i < $count; $i++) {
            $source = $pool[($rotation + $i) % count($pool)];
            $extension = pathinfo($source, PATHINFO_EXTENSION);
            $path = 'products/'.$product->id.'/'.Str::random(40).'.'.$extension;

            Storage::disk($disk)->put($path, File::get($source), 'public');

            $product->media()->create([
                'type' => 'image',
                'path' => $path,
                'url' => Storage::disk($disk)->url($path),
                'disk' => $disk,
                'mime_type' => File::mimeType($source),
                'size' => File::size($source),
                'order' => $i,
            ]);
        }
    }

    /**
     * Map each category slug to its sorted list of source image paths.
     *
     * @return array<string, list<string>>
     */
    private function buildPools(string $sourceDir): array
    {
        $pools = [];

        foreach (self::CATEGORY_IMAGES as $slug => $prefix) {
            $matches = File::glob($sourceDir.'/'.$prefix.'-*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
            sort($matches);

            if ($matches !== []) {
                $pools[$slug] = array_values($matches);
            }
        }

        return $pools;
    }

    private function info(string $message): void
    {
        $this->command?->info($message);
    }

    private function warn(string $message): void
    {
        $this->command?->warn($message);
    }
}
