<?php

use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use App\Models\User;
use App\Services\DeliverySettingsService;
use Database\Seeders\DeliveryRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function deliveryRate(float $fee = 40.00): DeliveryRate
{
    $service = DeliveryService::firstOrCreate(['slug' => 'amana'], ['name' => 'Amana', 'sort_order' => 1]);
    $city = City::create(['name' => 'Safi', 'region' => 'Marrakech-Safi']);

    return DeliveryRate::create([
        'delivery_service_id' => $service->id,
        'country_code' => 'MA',
        'country_name' => 'Maroc',
        'city_id' => $city->id,
        'fee' => $fee,
    ]);
}

test('the page shows the delivery settings, defaulting to destinations shown at 40 MAD', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->get(route('admin.delivery-rates.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/delivery-rates/index')
            ->where('settings.show_destinations', true)
            ->where('settings.flat_fee', 40)
        );
});

test('an admin can set the flat fee and hide cities and countries', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->patch(route('admin.delivery-rates.settings'), [
            'flat_fee' => '35.50',
            'show_destinations' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $settings = app(DeliverySettingsService::class);

    expect($settings->flatFee())->toBe(35.5)
        ->and($settings->showsDestinations())->toBeFalse();
});

test('the flat fee must be a non-negative number', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->patch(route('admin.delivery-rates.settings'), [
            'flat_fee' => '-5',
            'show_destinations' => true,
        ])
        ->assertSessionHasErrors('flat_fee');

    expect(app(DeliverySettingsService::class)->flatFee())->toBe(40.0);
});

test('an admin can change one rate line', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $rate = deliveryRate(40.00);

    $this->actingAs($admin)
        ->patch(route('admin.delivery-rates.update', $rate), ['fee' => '45'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect((float) $rate->fresh()->fee)->toBe(45.0);
});

test('non-admins cannot change delivery rates or settings', function () {
    $rate = deliveryRate(40.00);

    foreach (['buyer', 'seller'] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->patch(route('admin.delivery-rates.update', $rate), ['fee' => '1'])
            ->assertRoleDenied();

        $this->actingAs($user)
            ->patch(route('admin.delivery-rates.settings'), [
                'flat_fee' => '1',
                'show_destinations' => false,
            ])
            ->assertRoleDenied();
    }

    $settings = app(DeliverySettingsService::class);

    expect((float) $rate->fresh()->fee)->toBe(40.0)
        ->and($settings->showsDestinations())->toBeTrue();
});

/*
 * The seeder used to update fees on every run, which would now silently undo
 * whatever the desk set on the admin page.
 */
test('re-seeding the rate card keeps fees edited by the admin', function () {
    City::create(['name' => 'Safi', 'region' => 'Marrakech-Safi']);
    $this->seed(DeliveryRateSeeder::class);

    $rate = DeliveryRate::whereNotNull('city_id')->firstOrFail();
    $rate->update(['fee' => 55]);

    $this->seed(DeliveryRateSeeder::class);

    expect((float) $rate->fresh()->fee)->toBe(55.0)
        ->and(DeliveryRate::whereNotNull('city_id')->count())->toBe(1);
});
