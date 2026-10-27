<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantOption;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * A published product with `count` variants that all inherit its price — the
 * case that sends each variant back to its parent for the fallback price.
 */
function productWithInheritedPriceVariants(int $count): Product
{
    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier '.Str::random(5),
        'slug' => Str::slug('atelier-'.Str::random(6)),
        'status' => 'active',
    ]);

    $product = Product::create([
        'store_id' => $store->id,
        'name' => 'Vase',
        'slug' => Str::slug('vase-'.Str::random(6)),
        'base_price' => 100,
        'stock' => 0,
        'has_variants' => true,
        'status' => 'published',
    ]);

    foreach (range(1, $count) as $i) {
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'stock' => 5,
            'price_override' => null,
            'position' => $i,
            'is_active' => true,
        ]);

        ProductVariantOption::create([
            'product_variant_id' => $variant->id,
            'type' => 'color',
            'name' => 'Couleur',
            'value' => "Teinte {$i}",
        ]);
    }

    return $product;
}

/** How many times rendering the product page fetched a product by its id. */
function productByIdQueriesOnPage(Product $product): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Every variant still prices at the product's base price.
            ->where('product.variants.0.price', fn ($price) => (float) $price === 100.0)
            ->etc()
        );

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql) => preg_match('/from "products" where "products"\."id" = \?/', $sql));

    DB::disableQueryLog();

    return $queries->count();
}

it('does not re-query the product for each variant on the product page', function () {
    $one = productByIdQueriesOnPage(productWithInheritedPriceVariants(1));
    $five = productByIdQueriesOnPage(productWithInheritedPriceVariants(5));

    // Before Product::variants() used chaperone(), each variant without its
    // own price lazy-loaded its parent — one more query per variant.
    expect($five)->toBe($one);
});
