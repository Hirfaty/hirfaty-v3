<?php

use App\Models\Address;
use App\Models\City;
use App\Models\User;

/**
 * The buyer's account home: identity, account type, delivery addresses.
 */
beforeEach(function () {
    $this->city = City::create(['name' => 'Marrakech']);
    $this->buyer = User::factory()->create([
        'role' => 'buyer',
        'first_name' => 'Salma',
        'last_name' => 'Bennani',
        'email' => 'salma@example.ma',
    ]);
});

test('the account page shows who the buyer is', function () {
    $this->actingAs($this->buyer)
        ->get(route('buyer.dashboard'))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page->component('buyer/account/index')
                ->where('buyer.first_name', 'Salma')
                ->where('buyer.email', 'salma@example.ma')
                ->where('defaultAddress', null)
                ->has('cities', 1)
        );
});

// ── Identity ────────────────────────────────────────────────────────────

test('a buyer can change their own name', function () {
    $this->actingAs($this->buyer)
        ->patch(route('buyer.profile.update'), [
            'first_name' => 'Salma',
            'last_name' => 'El Fassi',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->buyer->fresh()->last_name)->toBe('El Fassi');
});

test('a name is required', function () {
    $this->actingAs($this->buyer)
        ->patch(route('buyer.profile.update'), ['first_name' => '', 'last_name' => ''])
        ->assertSessionHasErrors(['first_name', 'last_name']);

    expect($this->buyer->fresh()->first_name)->toBe('Salma');
});

/**
 * The email is what every password reset and verification link is sent to,
 * and the phone is what a courier calls. Both are rendered read-only on
 * /account, and the form request whitelisting the two name fields is what
 * actually enforces that — a hand-crafted request must not get through.
 */
test('the identity form cannot change the email or phone', function () {
    $this->buyer->forceFill([
        'phone_number' => '0611111111',
        'email_verified_at' => now(),
    ])->save();

    $this->actingAs($this->buyer)
        ->patch(route('buyer.profile.update'), [
            'first_name' => 'Salma',
            'last_name' => 'Bennani',
            'email' => 'autre@example.ma',
            'phone_number' => '0622222222',
        ])
        ->assertSessionHasNoErrors();

    expect($this->buyer->fresh())
        ->email->toBe('salma@example.ma')
        ->phone_number->toBe('0611111111')
        // And the verification it is gated on survives untouched.
        ->email_verified_at->not->toBeNull();
});

test('the identity form cannot grant a trade account', function () {
    $this->actingAs($this->buyer)->patch(route('buyer.profile.update'), [
        'first_name' => 'Salma',
        'last_name' => 'Bennani',
        'role' => 'professional_buyer',
        'professional_verified_at' => now(),
    ]);

    expect($this->buyer->fresh())
        ->role->toBe('buyer')
        ->professional_verified_at->toBeNull();
});

/**
 * The four pages share a sidebar but are separate routes, so each has to
 * stand up on its own for a buyer with no history at all.
 */
test('every page of the account area renders', function (string $name, string $component) {
    $this->actingAs($this->buyer)
        ->get(route($name))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['buyer.dashboard', 'buyer/account/index'],
    ['buyer.account.orders.index', 'buyer/orders/index'],
    ['buyer.wishlist', 'buyer/wishlists/index'],
    ['buyer.payment_methods', 'buyer/payment-methods/index'],
]);

test('the wishlist keeps its old singular URL as a redirect', function () {
    $this->actingAs($this->buyer)
        ->get('/wishlist')
        ->assertRedirect(route('buyer.wishlist'));
});

test('a seller cannot reach any page of the buyer account area', function (string $name) {
    $seller = User::factory()->create(['role' => 'seller']);

    $this->actingAs($seller)->get(route($name))->assertRoleDenied();
})->with([
    'buyer.dashboard',
    'buyer.account.orders.index',
    'buyer.wishlist',
    'buyer.payment_methods',
]);

// ── Addresses ───────────────────────────────────────────────────────────

function addressPayload(array $overrides = []): array
{
    return array_merge([
        'label' => 'Maison',
        // Required: Amana rings the buyer to deliver, so an address with no
        // number is one nobody can ship to.
        'recipient_phone' => '0612345678',
        'address_line' => '12 rue des Potiers, Guéliz',
        'city_id' => test()->city->id,
    ], $overrides);
}

/**
 * An address book with entries but no default would leave checkout with
 * nothing to pre-fill, which is the whole point of saving one — so the first
 * address becomes the default whether the box was ticked or not.
 */
test('the first saved address becomes the default automatically', function () {
    $this->actingAs($this->buyer)
        ->post(route('buyer.addresses.store'), addressPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $address = $this->buyer->addresses()->first();

    expect($address->is_default)->toBeTrue()
        ->and($address->address_line)->toBe('12 rue des Potiers, Guéliz');
});

test('saving a second address as default demotes the first', function () {
    $this->actingAs($this->buyer)->post(route('buyer.addresses.store'), addressPayload());
    $this->actingAs($this->buyer)->post(route('buyer.addresses.store'), addressPayload([
        'label' => 'Riad',
        'address_line' => '4 derb Zitoun, Médina',
        'is_default' => true,
    ]));

    $defaults = $this->buyer->addresses()->where('is_default', true)->get();

    // Exactly one default, always — the rule the service exists to hold.
    expect($defaults)->toHaveCount(1)
        ->and($defaults->first()->label)->toBe('Riad');
});

test('a second address without the flag does not steal the default', function () {
    $this->actingAs($this->buyer)->post(route('buyer.addresses.store'), addressPayload());
    $this->actingAs($this->buyer)->post(route('buyer.addresses.store'), addressPayload([
        'label' => 'Bureau',
        'address_line' => '9 avenue Mohammed V',
    ]));

    expect($this->buyer->addresses()->where('is_default', true)->count())->toBe(1)
        ->and($this->buyer->addresses()->where('is_default', true)->first()->label)
        ->toBe('Maison');
});

test('a buyer can promote another address to default', function () {
    $this->actingAs($this->buyer)->post(route('buyer.addresses.store'), addressPayload());
    $this->actingAs($this->buyer)->post(route('buyer.addresses.store'), addressPayload([
        'label' => 'Riad',
        'address_line' => '4 derb Zitoun',
    ]));

    $second = $this->buyer->addresses()->where('label', 'Riad')->first();

    $this->actingAs($this->buyer)
        ->patch(route('buyer.addresses.default', $second))
        ->assertRedirect();

    expect($second->fresh()->is_default)->toBeTrue()
        ->and($this->buyer->addresses()->where('is_default', true)->count())->toBe(1);
});

/**
 * Deleting the default must not leave a book with entries but no default —
 * checkout would have nothing to pre-fill even though addresses exist.
 */
test('deleting the default promotes another address', function () {
    $this->actingAs($this->buyer)->post(route('buyer.addresses.store'), addressPayload());
    $this->actingAs($this->buyer)->post(route('buyer.addresses.store'), addressPayload([
        'label' => 'Riad',
        'address_line' => '4 derb Zitoun',
    ]));

    $default = $this->buyer->addresses()->where('is_default', true)->first();

    $this->actingAs($this->buyer)
        ->delete(route('buyer.addresses.destroy', $default))
        ->assertRedirect();

    expect($this->buyer->addresses()->count())->toBe(1)
        ->and($this->buyer->addresses()->first()->is_default)->toBeTrue();
});

test('an address needs a line and a city', function () {
    $this->actingAs($this->buyer)
        ->post(route('buyer.addresses.store'), ['label' => 'Vide'])
        ->assertSessionHasErrors(['address_line', 'city_id', 'recipient_phone']);

    expect($this->buyer->addresses()->count())->toBe(0);
});

/**
 * Route-model binding will resolve any id, so ownership has to be checked
 * explicitly — otherwise anyone could re-point or delete someone else's
 * delivery address by guessing a number.
 */
test('a buyer cannot touch another buyer\'s address', function () {
    $other = User::factory()->create(['role' => 'buyer']);
    $theirs = Address::create([
        'user_id' => $other->id,
        'address_line' => '1 rue privée',
        'city_id' => $this->city->id,
        'is_default' => true,
    ]);

    $this->actingAs($this->buyer)
        ->patch(route('buyer.addresses.default', $theirs))
        ->assertForbidden();

    $this->actingAs($this->buyer)
        ->delete(route('buyer.addresses.destroy', $theirs))
        ->assertForbidden();

    expect($theirs->fresh())->not->toBeNull();
});

/**
 * /account shows the one address checkout pre-fills, not the whole book —
 * and it has to arrive shaped for the edit form, city_id included, or the
 * city select would come back empty on every visit.
 */
test('the account page carries the default address, not the others', function () {
    $this->actingAs($this->buyer)->post(route('buyer.addresses.store'), addressPayload());
    $this->actingAs($this->buyer)->post(route('buyer.addresses.store'), addressPayload([
        'label' => 'Riad',
        'address_line' => '4 derb Zitoun',
        'is_default' => true,
    ]));

    $this->actingAs($this->buyer)
        ->get(route('buyer.dashboard'))
        ->assertInertia(
            fn ($page) => $page->where('defaultAddress.label', 'Riad')
                ->where('defaultAddress.is_default', true)
                ->where('defaultAddress.city', 'Marrakech')
                ->where('defaultAddress.city_id', $this->city->id)
        );
});

test('a buyer can edit their default address in place', function () {
    $this->actingAs($this->buyer)->post(route('buyer.addresses.store'), addressPayload());
    $address = $this->buyer->addresses()->first();

    $this->actingAs($this->buyer)
        ->patch(route('buyer.addresses.update', $address), addressPayload([
            'label' => 'Riad',
            'address_line' => '4 derb Zitoun, Médina',
            'recipient_name' => 'Salma Bennani',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($address->fresh())
        ->label->toBe('Riad')
        ->address_line->toBe('4 derb Zitoun, Médina')
        ->recipient_name->toBe('Salma Bennani')
        // Editing must not disturb which address checkout pre-fills.
        ->is_default->toBeTrue();

    // Edited, not duplicated.
    expect($this->buyer->addresses()->count())->toBe(1);
});

test('an edited address still needs a line and a city', function () {
    $this->actingAs($this->buyer)->post(route('buyer.addresses.store'), addressPayload());
    $address = $this->buyer->addresses()->first();

    $this->actingAs($this->buyer)
        ->patch(route('buyer.addresses.update', $address), ['label' => 'Vide'])
        ->assertSessionHasErrors(['address_line', 'city_id', 'recipient_phone']);

    expect($address->fresh()->address_line)->toBe('12 rue des Potiers, Guéliz');
});

test('a buyer cannot edit another buyer\'s address', function () {
    $other = User::factory()->create(['role' => 'buyer']);
    $theirs = Address::create([
        'user_id' => $other->id,
        'address_line' => '1 rue privée',
        'city_id' => $this->city->id,
        'is_default' => true,
    ]);

    $this->actingAs($this->buyer)
        ->patch(route('buyer.addresses.update', $theirs), addressPayload())
        ->assertForbidden();

    expect($theirs->fresh()->address_line)->toBe('1 rue privée');
});
