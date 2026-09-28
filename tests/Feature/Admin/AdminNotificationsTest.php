<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\Admin\NewPurchaseAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function notificationsAdmin(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'super_admin',
        'email_verified_at' => now(),
    ], $attributes));
}

/**
 * A buyer with a two-workshop basket, ready to check out: 2 × 50 from one
 * store and 1 × 30 from another.
 */
function notificationsBasket(): User
{
    $buyer = User::factory()->create([
        'role' => 'buyer',
        'email_verified_at' => now(),
        'first_name' => 'Yasmine',
        'last_name' => 'Alaoui',
    ]);

    $cart = Cart::create(['user_id' => $buyer->id]);

    foreach ([['Alpha', 50.00, 2], ['Beta', 30.00, 1]] as [$name, $price, $qty]) {
        $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
        $store = Store::create([
            'user_id' => $seller->id,
            'store_name' => "{$name} Store",
            'slug' => Str::slug("{$name}-".Str::random(6)),
            'status' => 'active',
        ]);
        $product = Product::create([
            'store_id' => $store->id,
            'name' => "{$name} piece",
            'slug' => Str::slug("{$name}-piece-".Str::random(6)),
            'base_price' => $price,
            'stock' => 10,
            'status' => 'published',
        ]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $qty]);
    }

    return $buyer;
}

function notificationsCheckout(User $buyer)
{
    $city = City::create(['name' => 'Tangier', 'region' => 'Tangier-Tetouan-Al Hoceima']);

    return test()->actingAs($buyer)->post(route('checkout.store'), [
        'first_name' => 'Yasmine',
        'last_name' => 'Alaoui',
        'shipping_phone' => '0600000000',
        'shipping_address' => '12 Rue Test',
        'city_id' => $city->id,
        'postal_code' => '90000',
        'country' => 'MA',
        'payment_method' => 'cod',
    ]);
}

test('a checkout puts one purchase notification in every admin bell', function () {
    $first = notificationsAdmin();
    // No email on file: the bell must still reach them.
    $second = notificationsAdmin(['email' => null]);
    $buyer = notificationsBasket();

    notificationsCheckout($buyer)->assertSessionHasNoErrors();

    foreach ([$first, $second] as $admin) {
        expect($admin->notifications()->count())->toBe(1);

        $notification = $admin->notifications()->first();

        expect($notification->type)->toBe(NewPurchaseAlert::KIND)
            ->and($notification->read_at)->toBeNull()
            ->and($notification->data['buyer_name'])->toBe('Yasmine Alaoui')
            ->and($notification->data['store_count'])->toBe(2)
            ->and($notification->data['item_count'])->toBe(3)
            ->and((float) $notification->data['total'])->toBeGreaterThanOrEqual(130.0);
    }

    // Nobody else gets one.
    expect($buyer->notifications()->count())->toBe(0)
        ->and(User::where('role', 'seller')->get()->sum(fn ($seller) => $seller->notifications()->count()))->toBe(0);
});

test('the bell prop carries the unread count and latest rows for admins only', function () {
    $admin = notificationsAdmin();
    notificationsCheckout(notificationsBasket());

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('adminNotifications.unread_count', 1)
            ->has('adminNotifications.latest', 1)
            ->where('adminNotifications.latest.0.kind', NewPurchaseAlert::KIND)
            ->where('adminNotifications.latest.0.title', fn (string $title) => str_starts_with($title, 'Nouvelle commande'))
        );

    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);

    $this->actingAs($buyer)
        ->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page->where('adminNotifications', null));
});

test('opening a notification marks it read and leads to the purchase', function () {
    $admin = notificationsAdmin();
    notificationsCheckout(notificationsBasket());

    $notification = $admin->notifications()->first();

    $order = Order::findOrFail($notification->data['order_id']);

    $this->actingAs($admin)
        ->post(route('admin.notifications.open', $notification->id))
        ->assertRedirect(route('admin.orders.show', $order));

    // And that address is a real page, not a 404.
    $this->actingAs($admin)
        ->followingRedirects()
        ->post(route('admin.notifications.open', $notification->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/orders/show'));

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($admin->unreadNotifications()->count())->toBe(0);
});

test('an admin cannot open another admin\'s notification', function () {
    $owner = notificationsAdmin();
    $other = notificationsAdmin();
    notificationsCheckout(notificationsBasket());

    $notification = $owner->notifications()->first();

    $this->actingAs($other)
        ->post(route('admin.notifications.open', $notification->id))
        ->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('mark all read clears only the signed-in admin\'s notifications', function () {
    $admin = notificationsAdmin();
    $other = notificationsAdmin();
    notificationsCheckout(notificationsBasket());

    $this->actingAs($admin)
        ->post(route('admin.notifications.read-all'))
        ->assertRedirect();

    expect($admin->unreadNotifications()->count())->toBe(0)
        ->and($other->unreadNotifications()->count())->toBe(1);
});

test('the notifications page lists and filters the admin\'s notifications', function () {
    $admin = notificationsAdmin();
    notificationsCheckout(notificationsBasket());

    $this->actingAs($admin)
        ->get(route('admin.notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/notifications/index')
            ->has('notifications.data', 1)
            ->where('counts.all', 1)
            ->where('counts.unread', 1)
        );

    $admin->unreadNotifications()->update(['read_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.notifications.index', ['filter' => 'unread']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications.data', 0)
            ->where('filters.filter', 'unread')
        );
});

test('non-admins cannot reach the notifications page', function () {
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);

    $this->actingAs($buyer)
        ->get(route('admin.notifications.index'))
        ->assertRoleDenied();
});
