<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantOption;
use App\Models\Store;
use App\Models\User;

beforeEach(function () {
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

    $this->product = Product::create([
        'store_id' => $this->store->id,
        'category_id' => $this->category->id,
        'name' => 'Vase en terre',
        'slug' => 'vase-en-terre',
        'base_price' => 200,
        'stock' => 5,
        'status' => 'pending_review',
    ]);
});

test('the show page renders order history with the order relation resolved', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);
    $order = Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $this->store->id,
        'city_id' => City::first()->id,
        'status' => 'delivered',
        'total_amount' => 400,
        'platform_fee' => 0,
        'delivery_fee' => 0,
        'seller_earnings' => 380,
        'shipping_address' => 'Test Address',
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => 200,
        'subtotal' => 400,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.products.show', $this->product))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/products/show')
                ->where('product.id', $this->product->id)
                ->has('product.order_items', 1)
                ->where('product.order_items.0.order.id', $order->id)
        );
});

test('the show page exposes variants with their options', function () {
    $variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'sku' => 'VASE-BLEU',
        'stock' => 4,
        'price_override' => 250,
    ]);
    ProductVariantOption::create([
        'product_variant_id' => $variant->id,
        'type' => 'color',
        'name' => 'Couleur',
        'value' => 'Bleu Majorelle',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.products.show', $this->product))
        ->assertInertia(
            fn ($page) => $page
                ->has('product.variants', 1)
                ->where('product.variants.0.sku', 'VASE-BLEU')
                ->where('product.variants.0.stock', 4)
                ->has('product.variants.0.options', 1)
                // Axis and value, kept distinct.
                ->where('product.variants.0.options.0.name', 'Couleur')
                ->where('product.variants.0.options.0.value', 'Bleu Majorelle')
        );
});

test('approving a pending product publishes it and stamps the reviewer', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.products.approve', $this->product))
        ->assertRedirect();

    $this->product->refresh();

    expect($this->product->status)->toBe('published')
        ->and($this->product->reviewed_by)->toBe($this->admin->id)
        ->and($this->product->reviewed_at)->not->toBeNull();
});

test('rejecting a pending product requires a reason and notifies the seller', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.products.reject', $this->product), [
            'rejection_reason' => 'Photos de mauvaise qualité',
        ])
        ->assertRedirect();

    $this->product->refresh();

    expect($this->product->status)->toBe('rejected')
        ->and($this->product->rejection_reason)->toBe('Photos de mauvaise qualité');
});

test('rejecting without a reason fails validation', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.products.reject', $this->product), [])
        ->assertSessionHasErrors('rejection_reason');

    expect($this->product->fresh()->status)->toBe('pending_review');
});

test('archiving a published product removes it from the storefront', function () {
    $this->product->update(['status' => 'published']);

    $this->actingAs($this->admin)
        ->patch(route('admin.products.archive', $this->product))
        ->assertRedirect();

    expect($this->product->fresh()->status)->toBe('archived');
});

test('non admins cannot view or moderate a product', function () {
    foreach (['buyer', 'professional_buyer', 'seller'] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('admin.products.show', $this->product))
            ->assertRoleDenied();

        $this->actingAs($user)
            ->patch(route('admin.products.approve', $this->product))
            ->assertRoleDenied();
    }
});
