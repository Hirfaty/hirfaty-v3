<?php

use App\Models\Category;
use App\Models\City;
use App\Models\DeliveryService;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\CityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function cityStore(City $city, string $name): Store
{
    return Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => $name,
        'slug' => str($name)->slug()->toString(),
        'type' => 'artist',
        'category_id' => Category::firstOrCreate(
            ['slug' => 'poterie'],
            ['name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery']],
        )->id,
        'city_id' => $city->id,
        'status' => 'active',
    ]);
}

test('a city name resolves per locale and falls back to the French one', function () {
    $marrakech = City::create([
        'name' => 'Marrakech',
        'name_translations' => ['ar' => 'مراكش', 'en' => 'Marrakesh'],
    ]);
    $untranslated = City::create(['name' => 'Tahannaout']);

    expect($marrakech->nameFor('fr'))->toBe('Marrakech')
        ->and($marrakech->nameFor('ar'))->toBe('مراكش')
        ->and($marrakech->nameFor('en'))->toBe('Marrakesh')
        ->and($untranslated->nameFor('ar'))->toBe('Tahannaout');

    app()->setLocale('ar');
    expect($marrakech->toArray()['translated_name'])->toBe('مراكش');
});

test('known translations are found however the French name is typed', function () {
    $names = app(CityService::class);

    expect($names->knownTranslations('Fès'))->toBe(['ar' => 'فاس', 'en' => 'Fez'])
        ->and($names->knownTranslations('fes'))->toBe(['ar' => 'فاس', 'en' => 'Fez'])
        ->and($names->knownTranslations('Beni-Mellal')['ar'])->toBe('بني ملال')
        ->and($names->knownTranslations('Atlantis'))->toBe([]);
});

test('a shop page names its city in the language of the URL', function () {
    $city = City::create([
        'name' => 'Fès',
        'name_translations' => ['ar' => 'فاس', 'en' => 'Fez'],
    ]);
    $store = cityStore($city, 'Atelier Fassi');
    Product::create([
        'store_id' => $store->id,
        'name' => 'Zellige',
        'slug' => 'zellige',
        'base_price' => 90,
        'stock' => 3,
        'status' => 'published',
        'visibility' => 'both',
    ]);

    foreach (['fr' => 'Fès', 'ar' => 'فاس', 'en' => 'Fez'] as $locale => $expected) {
        $this->get("/{$locale}/stores/{$store->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('store.city_name', $expected)
                ->where('products.0.store.city_name', $expected));
    }
});

test('city pickers are named and sorted in the visitor\'s language', function () {
    City::create(['name' => 'Rabat', 'name_translations' => ['ar' => 'الرباط', 'en' => 'Rabat']]);
    City::create(['name' => 'Béni Mellal', 'name_translations' => ['ar' => 'بني ملال', 'en' => 'Beni Mellal']]);
    City::create(['name' => 'Casablanca', 'name_translations' => ['ar' => 'الدار البيضاء', 'en' => 'Casablanca']]);

    app()->setLocale('fr');
    // "Béni" sorts with the Bs, not after the Zs as a byte-wise sort has it.
    expect(app(CityService::class)->options()->pluck('name')->all())
        ->toBe(['Béni Mellal', 'Casablanca', 'Rabat']);

    app()->setLocale('ar');
    expect(app(CityService::class)->options()->pluck('name')->all())
        ->toBe(['الدار البيضاء', 'الرباط', 'بني ملال']);
});

test('the admin cities page lists names, shop counts and the ranking', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $agadir = City::create(['name' => 'Agadir', 'name_translations' => ['ar' => 'أكادير', 'en' => 'Agadir']]);
    $safi = City::create(['name' => 'Safi', 'region' => 'Marrakech-Safi']);
    City::create(['name' => 'Dakhla']);

    cityStore($safi, 'Poterie Safi');
    cityStore($safi, 'Atelier Safi');
    cityStore($agadir, 'Argan Agadir');

    $this->actingAs($admin)
        ->get(route('admin.cities.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/cities/index')
            ->where('totalStores', 3)
            ->has('cities', 3)
            ->where('cities.0', [
                'id' => $agadir->id,
                'name' => 'Agadir',
                'region' => null,
                'translations' => ['ar' => 'أكادير', 'en' => 'Agadir'],
                'stores_count' => 1,
            ])
            ->where('cities.2.translations', ['ar' => null, 'en' => null])
            ->where('cities.2.region', 'Marrakech-Safi')
            // Most shops first; a city with none is not on the chart.
            ->where('distribution', [
                ['id' => $safi->id, 'name' => 'Safi', 'stores_count' => 2],
                ['id' => $agadir->id, 'name' => 'Agadir', 'stores_count' => 1],
            ]));
});

test('the admin cities page is read-only', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)->post('/admin/cities', ['name' => 'Atlantis'])->assertMethodNotAllowed();

    expect(City::count())->toBe(0)
        ->and(Route::has(['admin.cities.store']))->toBeFalse()
        ->and(Route::has(['admin.cities.update']))->toBeFalse()
        ->and(Route::has(['admin.cities.destroy']))->toBeFalse();
});

test('a city added from the delivery rates keeps the names the desk typed, and fills the rest', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $service = DeliveryService::create(['slug' => 'amana', 'name' => 'Amana', 'sort_order' => 1]);

    $this->actingAs($admin)
        ->post(route('admin.delivery-rates.store'), [
            'delivery_service_id' => $service->id,
            'type' => 'city',
            'city_name' => 'Essaouira',
            'city_name_ar' => '',
            'city_name_en' => 'Mogador',
            'fee' => 45,
        ])
        ->assertSessionHasNoErrors();

    // English as typed; Arabic left blank, so the known name fills it in.
    expect(City::firstWhere('name', 'Essaouira')->name_translations)
        ->toBe(['en' => 'Mogador', 'ar' => 'الصويرة']);

    $this->actingAs($admin)
        ->post(route('admin.delivery-rates.store'), [
            'delivery_service_id' => $service->id,
            'type' => 'city',
            'city_name' => 'Tahannaout',
            'fee' => 45,
        ])
        ->assertSessionHasNoErrors();

    // Nothing typed and nothing known: the French name serves every language.
    expect(City::firstWhere('name', 'Tahannaout')->name_translations)->toBeNull();
});
