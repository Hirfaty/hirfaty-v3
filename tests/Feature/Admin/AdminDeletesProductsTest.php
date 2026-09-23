<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Media;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);

    $seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => $this->category->id,
        'city_id' => City::create(['name' => 'Fès'])->id,
    ]);
});

function productFor(int $storeId, int $categoryId, string $name = 'Vase en terre'): Product
{
    return Product::create([
        'store_id' => $storeId,
        'category_id' => $categoryId,
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(6),
        'base_price' => 200,
        'stock' => 5,
        'status' => 'published',
    ]);
}

test('admin can delete a product with no order history', function () {
    $product = productFor($this->store->id, $this->category->id);

    // Deleted from the product's own screen, so there is nothing to go back
    // to — this is the one case that lands on the catalogue.
    $this->actingAs($this->admin)
        ->from(route('admin.products.show', $product))
        ->delete(route('admin.products.destroy', $product))
        ->assertRedirect(route('admin.products.index'));

    expect(Product::find($product->id))->toBeNull();
});

test('deleting from elsewhere returns the admin to where they were', function () {
    $product = productFor($this->store->id, $this->category->id);
    $sellerPage = route('admin.users.show', $this->store->user_id);

    // The products table on a seller's profile offers the same action; being
    // thrown to the global catalogue afterwards would lose the admin's place.
    $this->actingAs($this->admin)
        ->from($sellerPage)
        ->delete(route('admin.products.destroy', $product))
        ->assertRedirect($sellerPage);

    expect(Product::find($product->id))->toBeNull();
});

test('deleting a product removes its media files from storage', function () {
    $product = productFor($this->store->id, $this->category->id);

    $path = UploadedFile::fake()->create('cover.jpg', 40, 'image/jpeg')->store("products/{$product->id}", 'public');
    $media = $product->media()->create([
        'type' => 'image',
        'path' => $path,
        'url' => Storage::disk('public')->url($path),
        'disk' => 'public',
        'order' => 0,
    ]);

    Storage::disk('public')->assertExists($path);

    $this->actingAs($this->admin)->delete(route('admin.products.destroy', $product));

    Storage::disk('public')->assertMissing($path);
    expect(Media::find($media->id))->toBeNull();
});

test('admin cannot delete a product that has order history', function () {
    $product = productFor($this->store->id, $this->category->id);
    $buyer = User::factory()->create(['role' => 'buyer']);

    $order = Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $this->store->id,
        'city_id' => City::first()->id,
        'status' => 'delivered',
        'total_amount' => 200,
        'platform_fee' => 0,
        'delivery_fee' => 0,
        'seller_earnings' => 200,
        'shipping_address' => 'Test Address',
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 200,
        'subtotal' => 200,
    ]);

    $response = $this->actingAs($this->admin)
        ->delete(route('admin.products.destroy', $product));

    $response->assertSessionHas('error');
    expect(session('error'))->toContain('order history');
    expect(Product::find($product->id))->not->toBeNull();
});

test('sellers cannot delete products via the admin route', function () {
    $seller = User::factory()->create(['role' => 'seller']);
    $product = productFor($this->store->id, $this->category->id);

    $this->actingAs($seller)
        ->delete(route('admin.products.destroy', $product))
        ->assertRoleDenied();

    expect(Product::find($product->id))->not->toBeNull();
});

test('bulk delete removes deletable products and skips ones with order history', function () {
    $deletableA = productFor($this->store->id, $this->category->id, 'Bol A');
    $deletableB = productFor($this->store->id, $this->category->id, 'Bol B');
    $ordered = productFor($this->store->id, $this->category->id, 'Bol Commande');

    $buyer = User::factory()->create(['role' => 'buyer']);
    $order = Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $this->store->id,
        'city_id' => City::first()->id,
        'status' => 'delivered',
        'total_amount' => 200,
        'platform_fee' => 0,
        'delivery_fee' => 0,
        'seller_earnings' => 200,
        'shipping_address' => 'Test Address',
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $ordered->id,
        'quantity' => 1,
        'unit_price' => 200,
        'subtotal' => 200,
    ]);

    $response = $this->actingAs($this->admin)->delete(route('admin.products.bulk-destroy'), [
        'ids' => [$deletableA->id, $deletableB->id, $ordered->id],
    ]);

    $response->assertSessionHas('success');
    expect(session('success'))->toContain('2 products deleted')
        ->and(session('success'))->toContain('Bol Commande');

    expect(Product::find($deletableA->id))->toBeNull()
        ->and(Product::find($deletableB->id))->toBeNull()
        ->and(Product::find($ordered->id))->not->toBeNull();
});

test('bulk delete requires at least one id', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.products.bulk-destroy'), ['ids' => []])
        ->assertSessionHasErrors('ids');
});
