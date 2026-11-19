<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\LoginCode;
use App\Models\Order;
use App\Models\PendingRegistration;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\LoginCodeNotification;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderShipped;
use App\Notifications\RegistrationCode;
use App\Services\CartService;
use App\Services\CheckoutContactService;
use App\Services\RegistrationCodeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Shopping without signing in: a basket kept against a cookie, a checkout
 * open to visitors, and the contact box that confirms an email or phone with
 * a code before anything can be paid for — which is how a mistyped address or
 * number is caught, and how the visitor ends up with an account.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

function guestShopProduct(string $name = 'Tajine', int $stock = 10): Product
{
    $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);

    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => "{$name} Store",
        'slug' => Str::slug("{$name}-store-".Str::random(6)),
        'status' => 'active',
    ]);

    return Product::create([
        'store_id' => $store->id,
        'name' => $name,
        'slug' => Str::slug("{$name}-".Str::random(6)),
        'base_price' => 120.00,
        'stock' => $stock,
        'status' => 'published',
    ]);
}

/**
 * Adds a piece as a visitor and hands back the basket cookie (plaintext — see
 * AuthFlowTest's startSignup for why) so later requests can carry it.
 */
function guestAdds(Product $product, int $quantity = 1): string
{
    $response = test()->post(route('cart.add'), [
        'product_id' => $product->id,
        'quantity' => $quantity,
    ])->assertSessionHasNoErrors();

    $cookie = $response->getCookie(CartService::GUEST_COOKIE)?->getValue();

    expect($cookie)->not->toBeNull();

    test()->withCookie(CartService::GUEST_COOKIE, $cookie);

    return $cookie;
}

/** The code the contact box mailed for a signup. */
function mailedSignupCode(): string
{
    $code = null;
    Notification::assertSentOnDemand(RegistrationCode::class, function (RegistrationCode $n) use (&$code) {
        $code = $n->code;

        return true;
    });

    return $code;
}

/** Carries the signup resume cookie a contact-box response set. */
function keepResumeCookie($response): void
{
    $cookie = $response->getCookie(RegistrationCodeService::COOKIE)?->getValue();

    if ($cookie) {
        test()->withCookie(RegistrationCodeService::COOKIE, $cookie);
    }
}

// ── The basket ──────────────────────────────────────────────────────────

test('a visitor can fill a basket without signing in', function () {
    $product = guestShopProduct();

    guestAdds($product, 2);

    $cart = Cart::whereNull('user_id')->first();

    expect($cart)->not->toBeNull()
        ->and($cart->guest_token_hash)->toHaveLength(64)
        ->and($cart->items)->toHaveCount(1)
        ->and($cart->items->first()->quantity)->toBe(2);

    $this->get(route('cart.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/cart')
            ->has('items', 1)
            // The header reads the same basket.
            ->where('cart.count', 2));
});

test('browsing without adding anything leaves no basket behind', function () {
    $this->get(route('cart.show'))->assertOk();

    expect(Cart::count())->toBe(0);
});

test('a visitor\'s basket is theirs alone', function () {
    $product = guestShopProduct();
    guestAdds($product);

    $item = CartItem::first();

    // Another browser: a different cookie, or none.
    $this->withCookie(CartService::GUEST_COOKIE, Str::random(48));

    $this->get(route('cart.show'))
        ->assertInertia(fn (Assert $page) => $page->has('items', 0));

    $this->patch(route('cart.update', $item), ['quantity' => 5])->assertNotFound();
    $this->delete(route('cart.remove', $item))->assertNotFound();

    expect($item->fresh()->quantity)->toBe(1);
});

test('a buyer cannot change a line in somebody else\'s basket', function () {
    $product = guestShopProduct();
    $owner = User::factory()->create(['role' => 'buyer']);
    $other = User::factory()->create(['role' => 'buyer']);

    $item = Cart::create(['user_id' => $owner->id])->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $this->actingAs($other)->delete(route('cart.remove', $item))->assertNotFound();

    expect($item->fresh())->not->toBeNull();
});

test('an artisan cannot shop', function () {
    $seller = User::factory()->create(['role' => 'seller']);

    $this->actingAs($seller)->get(route('cart.show'))->assertRoleDenied();
    $this->actingAs($seller)->get(route('checkout.show'))->assertRoleDenied();
});

test('signing in folds the visitor\'s basket into the account\'s', function () {
    $tajine = guestShopProduct('Tajine');
    $rug = guestShopProduct('Rug');

    $buyer = User::factory()->create(['role' => 'buyer', 'email' => 'known@example.com']);
    Cart::create(['user_id' => $buyer->id])->items()->create(['product_id' => $rug->id, 'quantity' => 1]);

    guestAdds($tajine, 2);
    guestAdds($rug);

    $this->post(route('login.store'), ['email' => 'known@example.com', 'password' => 'password']);

    $this->assertAuthenticatedAs($buyer);

    $cart = Cart::firstWhere('user_id', $buyer->id);

    expect($cart->items->pluck('quantity', 'product_id')->sortKeys()->all())->toEqual(collect([
        $rug->id => 2,
        $tajine->id => 2,
    ])->sortKeys()->all())->and(Cart::whereNull('user_id')->count())->toBe(0);
});

// ── Checkout for a visitor ─────────────────────────────────────────────

test('a visitor reaches checkout and is asked where to send the order', function () {
    guestAdds(guestShopProduct());

    $this->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/checkout')
            ->has('items', 1)
            ->where('addresses', [])
            ->where('contact.step', 'enter'));
});

test('a visitor cannot place the order before confirming a contact', function () {
    guestAdds(guestShopProduct());

    $this->post(route('checkout.store'), [])->assertRedirect(route('login'));

    expect(Order::count())->toBe(0);
});

test('a new email gets a code, and the code makes the account and keeps the basket', function () {
    $product = guestShopProduct();
    guestAdds($product, 2);
    $this->get(route('checkout.show'));

    $response = $this->post(route('checkout.contact.send'), ['contact' => 'Nadia@Example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('checkout.show'));
    keepResumeCookie($response);

    // The box now shows exactly where the code went, so a typo is visible.
    $this->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('contact.step', 'code')
            ->where('contact.value', 'nadia@example.com')
            ->where('contact.channel', 'email'));

    $this->post(route('checkout.contact.verify'), ['code' => mailedSignupCode()])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('checkout.show'));

    $user = User::firstWhere('email', 'nadia@example.com');

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('buyer')
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);

    // The basket came along.
    expect(Cart::firstWhere('user_id', $user->id)->items->first()->quantity)->toBe(2);

    $this->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('contact', null)
            ->where('customer.email', 'nadia@example.com'));
});

test('a phone number gets its code by SMS, and becomes the account', function () {
    $sms = fakeSms();
    guestAdds(guestShopProduct());
    $this->get(route('checkout.show'));

    $response = $this->post(route('checkout.contact.send'), ['contact' => '06 12 34 56 78'])
        ->assertSessionHasNoErrors();
    keepResumeCookie($response);

    $this->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('contact.step', 'code')
            ->where('contact.value', '+212 6 12 34 56 78')
            ->where('contact.channel', 'phone')
            ->where('contact.region', 'MA'));

    $this->post(route('checkout.contact.verify'), ['code' => $sms->codeTo('+212612345678')])
        ->assertSessionHasNoErrors();

    $user = User::firstWhere('phone_number', '+212612345678');

    expect($user->email)->toBeNull()
        ->and($user->phone_verified_at)->not->toBeNull();

    // The number is the delivery phone too, and an email is still asked for.
    $this->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('customer.phone', '+212612345678')
            ->where('customer.email', null)
            ->where('customer.contact_email', null));
});

test('an existing shopper\'s contact signs them in, with their saved basket', function () {
    $tajine = guestShopProduct('Tajine');
    $rug = guestShopProduct('Rug');

    $buyer = User::factory()->create(['role' => 'buyer', 'email' => 'known@example.com']);
    Cart::create(['user_id' => $buyer->id])->items()->create(['product_id' => $rug->id, 'quantity' => 1]);

    guestAdds($tajine);
    $this->get(route('checkout.show'));

    $this->post(route('checkout.contact.send'), ['contact' => 'known@example.com'])
        ->assertSessionHasNoErrors();

    $code = null;
    Notification::assertSentTo($buyer, LoginCodeNotification::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });

    expect(PendingRegistration::count())->toBe(0);

    $this->post(route('checkout.contact.verify'), ['code' => $code])->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($buyer);

    expect(Cart::firstWhere('user_id', $buyer->id)->items)->toHaveCount(2);
});

test('a wrong code keeps the visitor out, with the reason under the boxes', function () {
    guestAdds(guestShopProduct());
    $this->get(route('checkout.show'));

    $response = $this->post(route('checkout.contact.send'), ['contact' => 'nadia@example.com']);
    keepResumeCookie($response);

    $code = mailedSignupCode();
    $wrong = str_pad((string) ((((int) $code) + 1) % 1000000), 6, '0', STR_PAD_LEFT);

    $this->post(route('checkout.contact.verify'), ['code' => $wrong])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
    expect(User::where('email', 'nadia@example.com')->exists())->toBeFalse();
});

test('a contact that cannot be right is refused before any code is sent', function (string $typed) {
    guestAdds(guestShopProduct());
    $this->get(route('checkout.show'));

    $this->post(route('checkout.contact.send'), ['contact' => $typed])
        ->assertSessionHasErrors('contact');

    Notification::assertNothingSent();
})->with([
    'a number a digit short' => '06 12 34 56 7',
    'a number a digit long' => '06 12 34 56 789',
    'an address with no domain' => 'nadia@',
    'a word' => 'nadia',
]);

test('an artisan\'s contact cannot be used to check out', function () {
    $seller = User::factory()->create(['role' => 'seller', 'email' => 'atelier@example.com']);
    guestAdds(guestShopProduct());
    $this->get(route('checkout.show'));

    $this->post(route('checkout.contact.send'), ['contact' => 'atelier@example.com'])
        ->assertSessionHasErrors('contact');

    expect(LoginCode::count())->toBe(0);
    Notification::assertNothingSentTo($seller);
});

test('"change" goes back to the field', function () {
    guestAdds(guestShopProduct());
    $this->get(route('checkout.show'));

    $response = $this->post(route('checkout.contact.send'), ['contact' => 'nadia@exmaple.com']);
    keepResumeCookie($response);

    $this->post(route('checkout.contact.reset'))->assertRedirect();

    $this->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page->where('contact.step', 'enter'));

    expect(session()->has(CheckoutContactService::SESSION_KEY))->toBeFalse();
});

// ── Order updates by SMS ───────────────────────────────────────────────

test('a buyer with only a phone gets order updates by SMS, a buyer with an email by email', function () {
    $sms = fakeSms();
    $product = guestShopProduct();

    $byPhone = User::factory()->create([
        'role' => 'buyer',
        'email' => null,
        'phone_number' => '+212612345678',
        'phone_verified_at' => now(),
    ]);
    $byEmail = User::factory()->create(['role' => 'buyer']);

    // Only the reference matters to the text.
    $order = (new Order)->forceFill(['group_reference' => 'HRF-TEST01']);
    $orders = new Collection([$order]);

    expect((new OrderPlaced($orders))->via($byPhone))->toBe([SmsChannel::class])
        ->and((new OrderPlaced($orders))->via($byEmail))->toBe(['mail']);

    $text = (new OrderShipped($orders))->toSms($byPhone);

    expect($text)->toContain('HRF-TEST01')
        ->and($text)->toContain(route('buyer.purchases.tracking', 'HRF-TEST01'));

    // Through the channel, to the proven number.
    app(SmsChannel::class)->send($byPhone, new OrderShipped($orders));

    expect($sms->sent)->toHaveCount(1)
        ->and($sms->sent[0]['to'])->toBe('+212612345678');
});

test('an account with neither an email nor a proven number is not notified at all', function () {
    $sms = fakeSms();

    $user = User::factory()->create([
        'email' => null,
        'phone_number' => '+212612345678',
        'phone_verified_at' => null,
    ]);

    $user->notify(new OrderPlaced(new Collection));

    Notification::assertNothingSent();
    expect($sms->sent)->toBe([]);
});

// ── The email's link instead of the code ───────────────────────────────

/** The confirm link the contact box's signup email carried. */
function mailedSignupLink(): string
{
    $link = null;
    Notification::assertSentOnDemand(RegistrationCode::class, function (RegistrationCode $n) use (&$link) {
        $link = $n->url;

        return true;
    });

    return $link;
}

test('the link opened on another device lets the checkout finish by itself', function () {
    guestAdds(guestShopProduct(), 2);
    $this->get(route('checkout.show'));

    $response = $this->post(route('checkout.contact.send'), ['contact' => 'nadia@example.com'], ['referer' => route('checkout.show')]);
    $resume = $response->getCookie(RegistrationCodeService::COOKIE)->getValue();
    $link = mailedSignupLink();

    // The phone: no cookies of this browser. Confirming there is all it does.
    $basket = $this->defaultCookies;
    $this->defaultCookies = [];
    $this->post($link)->assertRedirect($link);
    $this->defaultCookies = $basket;

    // The checkout's box, polling, sees the confirmation…
    $this->withCookie(RegistrationCodeService::COOKIE, $resume)
        ->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page->where('contact.step', 'confirmed'));

    // …and finishes the signup here, without a code, basket and all.
    $this->withCookie(RegistrationCodeService::COOKIE, $resume)
        ->post(route('checkout.contact.verify'))
        ->assertSessionHasNoErrors();

    $user = User::firstWhere('email', 'nadia@example.com');

    $this->assertAuthenticatedAs($user);
    expect(Cart::firstWhere('user_id', $user->id)->items->first()->quantity)->toBe(2);
});

test('the link opened in this browser lands back on the checkout, not the tour', function () {
    guestAdds(guestShopProduct());
    $this->get(route('checkout.show'));

    $response = $this->post(route('checkout.contact.send'), ['contact' => 'nadia@example.com'], ['referer' => route('checkout.show')]);
    keepResumeCookie($response);

    $this->get(mailedSignupLink())->assertRedirect(route('login.details'));
    $this->get(route('login.details'))->assertRedirect('/checkout');

    $this->assertAuthenticated();

    // The checkout tab, polling, is now simply signed in.
    $this->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page->where('contact', null));
});

test('no code is accepted as "confirmed" unless the link really confirmed it', function () {
    guestAdds(guestShopProduct());
    $this->get(route('checkout.show'));

    $response = $this->post(route('checkout.contact.send'), ['contact' => 'nadia@example.com']);
    keepResumeCookie($response);

    $this->post(route('checkout.contact.verify'))->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('an existing shopper can confirm with the sign-in email\'s link, and the checkout signs in', function () {
    $buyer = User::factory()->create(['role' => 'buyer', 'email' => 'known@example.com']);
    guestAdds(guestShopProduct());
    $this->get(route('checkout.show'));

    $this->post(route('checkout.contact.send'), ['contact' => 'known@example.com'])->assertSessionHasNoErrors();

    $link = null;
    Notification::assertSentTo($buyer, LoginCodeNotification::class, function ($n) use (&$link) {
        $link = $n->url;

        return true;
    });

    // On the phone: confirm only.
    $asking = session()->all();
    $this->flushSession();
    $this->post($link)->assertRedirect($link);

    // The checkout, polling, sees it and finishes without a code.
    $this->flushSession();
    $this->withSession($asking)
        ->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page->where('contact.step', 'confirmed'));

    $this->post(route('checkout.contact.verify'))->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($buyer);
});
