<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Fills the catalog with published demo products, spread across the categories
 * that have test images available, then attaches those images.
 *
 * Idempotent: products are matched on their slug, so re-running only tops up
 * what is missing.
 *
 * Usage: php artisan db:seed --class=CatalogSeeder
 */
class CatalogSeeder extends Seeder
{
    /**
     * Existing seed rows whose category never matched their name.
     * name => correct category slug
     */
    private const REALIGN = [
        'Sac en cuir naturel' => 'leather-goods',
        'Ceinture en cuir tanné' => 'leather-goods',
        'Portefeuille artisanal' => 'leather-goods',
        'Babouches en cuir doré' => 'leather-goods',
        'Pochette brodée traditionnelle' => 'leather-goods',
    ];

    /**
     * category slug => list of [name, description, base_price, sale_price, stock]
     */
    private const PRODUCTS = [
        'pottery-ceramics' => [
            ['Tajine émaillé de Safi', 'Tajine en terre cuite émaillée, tourné et peint à la main à Safi.', 240, null, 12],
            ['Plat à couscous peint main', 'Grand plat à couscous décoré de motifs géométriques traditionnels.', 380, 320, 6],
            ['Vase en céramique de Fès', 'Vase bleu de Fès, cuisson traditionnelle au four à bois.', 195, null, 9],
        ],
        'textiles-weaving' => [
            ['Tapis berbère Beni Ouarain', 'Tapis en laine vierge noué main dans le Moyen Atlas, motifs losanges.', 2400, 2100, 3],
            ['Couverture handira nuptiale', 'Handira brodée de sequins, pièce de mariage du Haut Atlas.', 1300, null, 4],
            ['Coussin tissé à la main', 'Coussin en coton tissé sur métier traditionnel, teintures naturelles.', 180, null, 20],
        ],
        'jewelry-accessories' => [
            ['Collier berbère en argent', 'Collier en argent massif serti de corail et d\'ambre.', 890, 750, 5],
            ['Bracelet manchette gravé', 'Manchette en argent ciselée à la main par un maître graveur de Tiznit.', 460, null, 8],
            ['Boucles d\'oreilles filigrane', 'Filigrane d\'argent travaillé au fil fin, finition polie main.', 320, null, 14],
        ],
        'wood-carpentry' => [
            ['Plateau en bois de thuya', 'Plateau en ronce de thuya d\'Essaouira, huilé à la cire d\'abeille.', 350, null, 10],
            ['Boîte à bijoux marquetée', 'Marqueterie de thuya et de citronnier, charnières laiton.', 520, 450, 7],
            ['Miroir sculpté en cèdre', 'Cadre en cèdre de l\'Atlas sculpté de motifs andalous.', 780, null, 4],
        ],
        'painting-art' => [
            ['Toile calligraphique moderne', 'Acrylique sur toile, calligraphie arabe contemporaine signée.', 1600, null, 2],
            ['Aquarelle des remparts d\'Essaouira', 'Aquarelle originale sur papier grain fin, encadrée.', 900, 780, 3],
            ['Peinture zellige abstraite', 'Composition abstraite inspirée du zellige de Meknès.', 1150, null, 2],
        ],
        'leather-goods' => [
            ['Pouf en cuir de Marrakech', 'Pouf rond en cuir tanné végétal, cousu main, à garnir.', 620, 540, 6],
            ['Cartable en cuir de chèvre', 'Cartable souple en cuir de chèvre tanné aux écorces.', 980, null, 5],
        ],
    ];

    public function run(): void
    {
        $stores = Store::where('status', 'active')->orderBy('id')->get();

        if ($stores->isEmpty()) {
            $this->command?->error('No active store found — seed stores first (TestSeeder).');

            return;
        }

        $categories = Category::pluck('id', 'slug');
        $this->realignCategories($categories);

        $created = 0;
        $storeIndex = 0;

        foreach (self::PRODUCTS as $slug => $rows) {
            $categoryId = $categories[$slug] ?? null;

            if (! $categoryId) {
                $this->command?->warn("Category \"{$slug}\" missing — run CategorySeeder first.");

                continue;
            }

            foreach ($rows as [$name, $description, $basePrice, $salePrice, $stock]) {
                $productSlug = Str::slug($name);

                // Round-robin so every store ends up with something to show.
                $store = $stores[$storeIndex % $stores->count()];
                $storeIndex++;

                $product = Product::firstOrCreate(
                    ['slug' => $productSlug],
                    [
                        'store_id' => $store->id,
                        'category_id' => $categoryId,
                        'name' => $name,
                        'description' => $description,
                        'base_price' => $basePrice,
                        'sale_price' => $salePrice,
                        'stock' => $stock,
                        'status' => 'published',
                    ]
                );

                if ($product->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        $this->command?->info("✅ Catalog: {$created} product(s) created.");

        $this->call(ProductImageSeeder::class);
    }

    /**
     * Point the mis-filed demo products at the category their name describes,
     * so they receive matching images.
     *
     * @param  Collection<string, int>  $categories
     */
    private function realignCategories($categories): void
    {
        foreach (self::REALIGN as $name => $slug) {
            $categoryId = $categories[$slug] ?? null;

            if ($categoryId) {
                Product::where('name', $name)
                    ->where('category_id', '!=', $categoryId)
                    ->update(['category_id' => $categoryId]);
            }
        }
    }
}
