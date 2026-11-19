<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\OrderPlaced;
use App\Services\ContactAccountService;
use App\Support\Contact;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A buyer who signed in by phone has no email on file: checkout asks for one
 * beside the delivery phone, and keeps it as `contact_email` — never as the
 * sign-in address, since nothing proved it is theirs.
 */

beforeEach(function () {
    Notification::fake();
    Http::preventStrayRequests();

    $cathedis = DeliveryService::create(['slug' => 'cathedis', 'name' => 'Cathedis', 'sort_order' => 1]);
    $this->casa = City::create(['name' => 'Casablanca', 'region' => 'Casablanca-Settat']);
    DeliveryRate::create([
        'delivery_service_id' => $cathedis->id,
        'country_code' => 'MA',
        'country_name' => 'Maroc',
        'city_id' => $this->casa->id,
        'fee' => 20,
    ]);
});

function contactEmailBuyer(array $attributes = []): User
{
    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier '.Str::random(4),
        'slug' => 'atelier-'.Str::random(8),
        'status' => 'active',
    ]);
    $product = Product::create([
        'store_id' => $store->id,
        'name' => 'Bol',
        'slug' => 'bol-'.Str::random(8),
        'base_price' => 200,
        'stock' => 20,
        'status' => 'published',
    ]);

    $buyer = User::factory()->create(array_merge(['role' => 'buyer'], $attributes));
    $cart = Cart::create(['user_id' => $buyer->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

    return $buyer;
}

function phoneOnlyBuyer(): User
{
    return contactEmailBuyer([
        'email' => null,
        'email_verified_at' => null,
        'phone_number' => '+212612345678',
        'phone_verified_at' => now(),
    ]);
}

/** @return array<string, mixed> */
function contactEmailCheckout(int $cityId, array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Yasmine',
        'last_name' => 'Alaoui',
        'shipping_phone' => '0612345678',
        'shipping_address' => '12 rue Fès',
        'postal_code' => '20000',
        'city_id' => $cityId,
        'country' => 'MA',
        'payment_method' => 'card',
    ], $overrides);
}

test('a buyer signed in by phone must give an email to check out', function () {
    $buyer = phoneOnlyBuyer();

    $this->actingAs($buyer)
        ->from(route('checkout.show'))
        ->post(route('checkout.store'), contactEmailCheckout($this->casa->id))
        ->assertSessionHasErrors(['email' => 'Indiquez une adresse e-mail valide.']);

    $this->actingAs($buyer)
        ->from(route('checkout.show'))
        ->post(route('checkout.store'), contactEmailCheckout($this->casa->id, ['email' => 'pas-une-adresse']))
        ->assertSessionHasErrors('email');

    expect(Order::withoutGlobalScope(Order::SETTLED_SCOPE)->count())->toBe(0);
});

test('the email is kept as a contact address, not as the sign-in one', function () {
    $buyer = phoneOnlyBuyer();

    $this->actingAs($buyer)
        ->post(route('checkout.store'), contactEmailCheckout($this->casa->id, ['email' => '  Yasmine@Example.com ']))
        ->assertSessionHasNoErrors();

    $buyer->refresh();

    expect(Order::withoutGlobalScope(Order::SETTLED_SCOPE)->count())->toBe(1)
        ->and($buyer->contact_email)->toBe('yasmine@example.com')
        ->and($buyer->email)->toBeNull()
        ->and($buyer->reachableEmail())->toBe('yasmine@example.com')
        // It opens nothing: no code can be sent to it to sign in.
        ->and(app(ContactAccountService::class)->find(Contact::parse('yasmine@example.com')))->toBeNull()
        // And order updates still go to the proven number.
        ->and((new OrderPlaced(new Collection))->via($buyer))->not->toBe(['mail']);

    // Pre-filled on the next visit.
    $this->actingAs($buyer)
        ->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('customer.email', null)
            ->where('customer.contact_email', 'yasmine@example.com')
            ->etc());
});

test('a buyer with a sign-in email is not asked again, and what is posted is ignored', function () {
    $buyer = contactEmailBuyer(['email' => 'nadia@example.com', 'email_verified_at' => now()]);

    $this->actingAs($buyer)
        ->post(route('checkout.store'), contactEmailCheckout($this->casa->id, ['email' => 'other@example.com']))
        ->assertSessionHasNoErrors();

    $buyer->refresh();

    expect($buyer->email)->toBe('nadia@example.com')
        ->and($buyer->contact_email)->toBeNull();
});
