<?php

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** A selling shop with `count` published products, each with one photo. */
function shopWithPublishedProducts(int $count): Store
{
    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier '.Str::random(5),
        'slug' => Str::slug('atelier-'.Str::random(6)),
        'status' => 'active',
    ]);

    foreach (range(1, $count) as $i) {
        $product = Product::create([
            'store_id' => $store->id,
            'name' => "Vase {$i}",
            'slug' => Str::slug("vase-{$i}-".Str::random(6)),
            'base_price' => 100,
            'stock' => 5,
            'status' => 'published',
        ]);

        $product->media()->create(['type' => 'image', 'path' => "vase-{$i}.jpg", 'url' => "/storage/vase-{$i}.jpg", 'order' => 0]);
    }

    return $store;
}

/** How many media queries rendering the home page ran. */
function mediaQueriesOnHomePage(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->get(route('home', ['locale' => 'fr']))->assertOk();

    $count = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql) => str_contains($sql, 'from "media"'))
        ->count();

    DB::disableQueryLog();

    return $count;
}

it('does not query media once per product on the home page', function () {
    shopWithPublishedProducts(1);
    $one = mediaQueriesOnHomePage();

    shopWithPublishedProducts(5);
    $six = mediaQueriesOnHomePage();

    // Each card used to lazy-load its videos — one more query per product.
    expect($six)->toBe($one);
});

it('still sends a product its videos on the product page', function () {
    $product = shopWithPublishedProducts(1)->products()->first();
    $product->media()->create(['type' => 'video', 'path' => 'atelier.mp4', 'url' => '/storage/atelier.mp4', 'order' => 1]);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('product.videos', 1)
            ->where('product.videos.0.url', '/storage/atelier.mp4')
            ->etc()
        );
});
