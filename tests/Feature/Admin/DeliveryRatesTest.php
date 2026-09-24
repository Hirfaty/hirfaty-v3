<?php

use App\Models\Address;
use App\Models\Cart;
use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use App\Models\User;
use App\Services\DeliveryFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);
    $this->service = DeliveryService::create(['slug' => 'amana', 'name' => 'Amana', 'sort_order' => 1]);
    $this->casa = City::create(['name' => 'Casablanca', 'region' => 'Casablanca-Settat']);
    $this->fes = City::create(['name' => 'Fès', 'region' => 'Fès-Meknès']);

    $this->casaRate = DeliveryRate::create([
        'delivery_service_id' => $this->service->id,
        'country_code' => 'MA',
        'country_name' => 'Maroc',
        'city_id' => $this->casa->id,
        'fee' => 40,
    ]);
    $this->franceRate = DeliveryRate::create([
        'delivery_service_id' => $this->service->id,
        'country_code' => 'FR',
        'country_name' => 'France',
        'city_id' => null,
        'fee' => 120,
    ]);
});

test('the page lists the card and the cities that have no rate yet', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.delivery-rates.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/delivery-rates/index')
            ->has('rates', 2)
            ->where('availableCities', [['id' => $this->fes->id, 'name' => 'Fès']]));
});

test('only a super admin can change the card', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($buyer)
        ->patch(route('admin.delivery-rates.update', $this->casaRate), ['fee' => 99])
        ->assertRedirect(route('products.index'));

    expect((float) $this->casaRate->fresh()->fee)->toBe(40.0);
});

test('the desk can change the fee of a line', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.delivery-rates.update', $this->franceRate), ['fee' => '150.50'])
        ->assertSessionHasNoErrors();

    expect((float) $this->franceRate->fresh()->fee)->toBe(150.5);
});

test('a fee must be a non-negative number', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.delivery-rates.update', $this->casaRate), ['fee' => '-5'])
        ->assertSessionHasErrors('fee');

    $this->actingAs($this->admin)
        ->patch(route('admin.delivery-rates.update', $this->casaRate), ['fee' => 'abc'])
        ->assertSessionHasErrors('fee');

    expect((float) $this->casaRate->fresh()->fee)->toBe(40.0);
});

test('an existing city can be added to the card', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.delivery-rates.store'), [
            'delivery_service_id' => $this->service->id,
            'type' => 'city',
            'city_id' => $this->fes->id,
            'fee' => 35,
        ])
        ->assertSessionHasNoErrors();

    $rate = DeliveryRate::firstWhere('city_id', $this->fes->id);

    expect($rate->country_code)->toBe('MA')
        ->and((float) $rate->fee)->toBe(35.0);
});

test('a new city can be created and priced in one go', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.delivery-rates.store'), [
            'delivery_service_id' => $this->service->id,
            'type' => 'city',
            'city_name' => 'Ouarzazate',
            'region' => 'Drâa-Tafilalet',
            'fee' => 55,
        ])
        ->assertSessionHasNoErrors();

    $city = City::firstWhere('name', 'Ouarzazate');

    expect($city)->not->toBeNull()
        ->and($city->region)->toBe('Drâa-Tafilalet')
        ->and(DeliveryRate::where('city_id', $city->id)->value('fee'))->toEqual(55);
});

test('a city cannot be priced twice, and a new city cannot reuse a name', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.delivery-rates.store'), [
            'delivery_service_id' => $this->service->id,
            'type' => 'city',
            'city_id' => $this->casa->id,
            'fee' => 10,
        ])
        ->assertSessionHasErrors('city_id');

    $this->actingAs($this->admin)
        ->post(route('admin.delivery-rates.store'), [
            'delivery_service_id' => $this->service->id,
            'type' => 'city',
            'city_name' => 'Casablanca',
            'fee' => 10,
        ])
        ->assertSessionHasErrors('city_name');

    expect(DeliveryRate::where('city_id', $this->casa->id)->count())->toBe(1);
});

test('a city needs either an existing city or a new name', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.delivery-rates.store'), [
            'delivery_service_id' => $this->service->id,
            'type' => 'city',
            'fee' => 10,
        ])
        ->assertSessionHasErrors(['city_id', 'city_name']);
});

test('a country can be added with a single fee', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.delivery-rates.store'), [
            'delivery_service_id' => $this->service->id,
            'type' => 'country',
            'country_code' => 'pt',
            'country_name' => 'Portugal',
            'fee' => 130,
        ])
        ->assertSessionHasNoErrors();

    $rate = DeliveryRate::firstWhere('country_code', 'PT');

    expect($rate->city_id)->toBeNull()
        ->and($rate->country_name)->toBe('Portugal')
        ->and((float) $rate->fee)->toBe(130.0);
});

test('a country cannot be added twice, and Morocco is priced per city only', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.delivery-rates.store'), [
            'delivery_service_id' => $this->service->id,
            'type' => 'country',
            'country_code' => 'FR',
            'country_name' => 'France',
            'fee' => 100,
        ])
        ->assertSessionHasErrors('country_code');

    $this->actingAs($this->admin)
        ->post(route('admin.delivery-rates.store'), [
            'delivery_service_id' => $this->service->id,
            'type' => 'country',
            'country_code' => 'MA',
            'country_name' => 'Maroc',
            'fee' => 100,
        ])
        ->assertSessionHasErrors('country_code');

    $this->actingAs($this->admin)
        ->post(route('admin.delivery-rates.store'), [
            'delivery_service_id' => $this->service->id,
            'type' => 'country',
            'country_code' => 'PRT',
            'country_name' => 'Portugal',
            'fee' => 100,
        ])
        ->assertSessionHasErrors('country_code');
});

test('removing a city drops its rate but keeps the city itself', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.delivery-rates.destroy', $this->casaRate))
        ->assertSessionHasNoErrors();

    expect(DeliveryRate::find($this->casaRate->id))->toBeNull()
        ->and(City::find($this->casa->id))->not->toBeNull();
});

test('removing a country closes it at checkout', function () {
    $fees = app(DeliveryFeeService::class);

    expect($fees->shippableCountryCodes())->toContain('FR');

    $this->actingAs($this->admin)
        ->delete(route('admin.delivery-rates.destroy', $this->franceRate));

    expect($fees->shippableCountryCodes())->not->toContain('FR');
});

test('checkout offers only the cities on the card, and refuses the others', function () {
    $fees = app(DeliveryFeeService::class);
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
    Cart::create(['user_id' => $buyer->id]);

    expect($fees->deliverableCityIds())->toBe([$this->casa->id]);

    // Fès has no rate: not selectable.
    $this->actingAs($buyer)
        ->post(route('checkout.store'), ['country' => 'MA', 'city_id' => $this->fes->id])
        ->assertSessionHasErrors('city_id');

    // Casablanca is on the card, so the city rule passes.
    $this->actingAs($buyer)
        ->post(route('checkout.store'), ['country' => 'MA', 'city_id' => $this->casa->id])
        ->assertSessionDoesntHaveErrors('city_id');
});

test('a card with no cities leaves every city selectable', function () {
    $this->casaRate->delete();

    expect(app(DeliveryFeeService::class)->deliverableCityIds())->toBeNull();
});

test('saved addresses the card no longer serves are left out', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);

    $address = fn (array $attributes) => Address::create(array_merge([
        'user_id' => $buyer->id,
        'label' => 'Maison',
        'recipient_name' => 'Yasmine',
        'recipient_phone' => '0600000000',
        'address_line' => '1 rue Test',
        'postal_code' => '10000',
    ], $attributes));

    $open = $address(['country' => 'MA', 'city_id' => $this->casa->id]);
    $closedCity = $address(['country' => 'MA', 'city_id' => $this->fes->id]);
    $abroad = $address(['country' => 'FR', 'city_id' => null]);
    $closedCountry = $address(['country' => 'DE', 'city_id' => null]);

    $served = app(DeliveryFeeService::class)
        ->scopeToDeliverable(Address::query()->where('user_id', $buyer->id))
        ->pluck('id')
        ->all();

    expect($served)->toEqualCanonicalizing([$open->id, $abroad->id])
        ->and($served)->not->toContain($closedCity->id, $closedCountry->id);
});
