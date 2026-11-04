<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The seller profile at /admin/users/{id}.
 *
 * The products table there offers view / edit / delete per row, and Delete is
 * the one that can fail: ProductDeletionService refuses a product with order
 * history, because order_items cascades and removing the row would wipe the
 * line off historical invoices. The page therefore needs to know, per product,
 * whether it has ever been ordered — that is what these tests pin down.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);
    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);
    $this->city = City::create(['name' => 'Safi']);

    $this->seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $this->seller->id,
        'store_name' => 'Atelier Safi',
        'slug' => 'atelier-safi-'.Str::random(5),
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
    ]);
});

function sellerProduct(Store $store, Category $category, string $name): Product
{
    return Product::create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(5),
        'base_price' => 150,
        'stock' => 4,
        'status' => 'published',
    ]);
}

test('the admin user pages receive the french name of the category', function () {
    $this->actingAs($this->admin)
        ->withSession(['locale' => 'en'])
        ->get(route('admin.users.show', $this->seller))
        ->assertInertia(fn (Assert $page) => $page->where('user.store.category.name.fr', 'Poterie'));

    $this->get(route('admin.users.create'))
        ->assertInertia(fn (Assert $page) => $page->where('categories.0.name.fr', 'Poterie'));

    $this->get(route('admin.users.edit', $this->seller))
        ->assertInertia(fn (Assert $page) => $page->where('categories.0.name.fr', 'Poterie'));
});

test('the seller page carries an order count per product', function () {
    $untouched = sellerProduct($this->store, $this->category, 'Vase libre');
    $ordered = sellerProduct($this->store, $this->category, 'Vase vendu');

    $order = Order::create([
        'buyer_id' => User::factory()->create(['role' => 'buyer'])->id,
        'store_id' => $this->store->id,
        'city_id' => $this->city->id,
        'status' => 'pending',
        'total_amount' => 150,
        'shipping_address' => '1 Rue Test',
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $ordered->id,
        'quantity' => 1,
        'unit_price' => 150,
        'subtotal' => 150,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.users.show', $this->seller))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($untouched, $ordered) {
            $page->component('admin/users/showSeller');

            $products = collect($page->toArray()['props']['user']['store']['products']);

            // Drives whether the row offers Delete at all. Without the count
            // the page would offer it on every product and the server would
            // refuse half of them.
            expect($products->firstWhere('id', $untouched->id)['order_items_count'])->toBe(0)
                ->and($products->firstWhere('id', $ordered->id)['order_items_count'])->toBe(1);
        });
});

test('the seller page still lists products with their images and category', function () {
    $product = sellerProduct($this->store, $this->category, 'Plat émaillé');
    $product->media()->create([
        'type' => 'image',
        'path' => 'products/plat.jpg',
        'url' => 'https://example.test/plat.jpg',
        'disk' => 'public',
        'order' => 0,
    ]);

    // Adding withCount to the products eager load must not cost the nested
    // relations the table renders alongside it.
    $this->actingAs($this->admin)
        ->get(route('admin.users.show', $this->seller))
        ->assertInertia(function (Assert $page) {
            $product = collect($page->toArray()['props']['user']['store']['products'])->first();

            expect($product['images'][0]['url'])->toBe('https://example.test/plat.jpg')
                ->and($product['category'])->not->toBeNull();
        });
});
