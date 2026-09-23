<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Store;
use App\Models\User;
use App\Notifications\WelcomeUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * The CEO onboards every account from the admin panel, so all three creatable
 * roles are covered here.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);
});

/**
 * A real (tiny) PNG, not a garbage blob with a .jpg name — the logo/banner
 * dimension rule reads actual pixel data via getimagesize(), which a fake
 * ->create() blob can't satisfy. GD isn't installed in this environment, so
 * UploadedFile::fake()->image() (which needs it) isn't an option either.
 */
function fakeImageUpload(string $name): UploadedFile
{
    $onePixelPng = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
    );

    return UploadedFile::fake()->createWithContent($name, $onePixelPng);
}

test('admin creates a seller together with their store', function () {
    $category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);
    $city = City::create(['name' => 'Safi']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Hassan',
            'last_name' => 'Alaoui',
            'email' => 'hassan@artisan.ma',
            'phone_number' => '0612345678',
            'role' => 'seller',
            'store_name' => 'Poterie Safi',
            'category_id' => $category->id,
            'city_id' => $city->id,
        ])
        ->assertRedirect();

    $user = User::firstWhere('email', 'hassan@artisan.ma');
    $store = Store::firstWhere('user_id', $user->id);

    expect($user->role)->toBe('seller')
        // Admin-created accounts are pre-verified so they can log in directly.
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($store->store_name)->toBe('Poterie Safi')
        // No type was sent — falls back to the DB/controller default.
        ->and($store->type)->toBe('artist');
});

test('admin sets the seller type and uploads shop photos', function () {
    Storage::fake('public');

    $category = Category::create([
        'slug' => 'bijoux',
        'name' => ['fr' => 'Bijoux', 'ar' => 'مجوهرات', 'en' => 'Jewelry'],
    ]);
    $city = City::create(['name' => 'Rabat']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Nadia',
            'last_name' => 'Cherkaoui',
            'email' => 'nadia@cooperative.ma',
            'phone_number' => '0698112233',
            'role' => 'seller',
            'store_name' => 'Coopérative Argent',
            'store_type' => 'cooperative',
            'category_id' => $category->id,
            'city_id' => $city->id,
            'logo' => fakeImageUpload('logo.png'),
            'banner' => fakeImageUpload('banner.png'),
        ])
        ->assertRedirect();

    $store = Store::firstWhere('store_name', 'Coopérative Argent');

    expect($store->type)->toBe('cooperative')
        ->and($store->logo)->not->toBeNull()
        ->and($store->banner)->not->toBeNull()
        ->and($store->logo)->not->toBe($store->banner);
});

test('an invalid seller type is rejected', function () {
    $category = Category::create([
        'slug' => 'textiles',
        'name' => ['fr' => 'Textiles', 'ar' => 'منسوجات', 'en' => 'Textiles'],
    ]);
    $city = City::create(['name' => 'Tanger']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'X',
            'last_name' => 'Y',
            'email' => 'xy@artisan.ma',
            'role' => 'seller',
            'store_name' => 'Atelier XY',
            'store_type' => 'individual',
            'category_id' => $category->id,
            'city_id' => $city->id,
        ])
        ->assertSessionHasErrors('store_type');
});

test('a shop photo must be an image', function () {
    $category = Category::create([
        'slug' => 'ceramique',
        'name' => ['fr' => 'Céramique', 'ar' => 'خزف', 'en' => 'Ceramics'],
    ]);
    $city = City::create(['name' => 'Meknès']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'ab@artisan.ma',
            'role' => 'seller',
            'store_name' => 'Atelier AB',
            'category_id' => $category->id,
            'city_id' => $city->id,
            'logo' => UploadedFile::fake()->create('doc.pdf', 40, 'application/pdf'),
        ])
        ->assertSessionHasErrors('logo');
});

test('an oversized shop photo is rejected', function () {
    $category = Category::create([
        'slug' => 'cuir',
        'name' => ['fr' => 'Cuir', 'ar' => 'جلد', 'en' => 'Leather'],
    ]);
    $city = City::create(['name' => 'Fès']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'C',
            'last_name' => 'D',
            'email' => 'cd@artisan.ma',
            'role' => 'seller',
            'store_name' => 'Atelier CD',
            'category_id' => $category->id,
            'city_id' => $city->id,
            // Over the 5 MB config('media.image.max_kb') ceiling.
            'logo' => UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg'),
        ])
        ->assertSessionHasErrors('logo');
});

test('admin creates a professional buyer', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Nadia',
            'last_name' => 'Benali',
            'email' => 'nadia@grossiste.ma',
            'phone_number' => '0698765432',
            'role' => 'professional_buyer',
        ])
        ->assertRedirect();

    $user = User::firstWhere('email', 'nadia@grossiste.ma');

    expect($user->role)->toBe('professional_buyer')
        ->and($user->isProfessionalBuyer())->toBeTrue()
        // A professional buyer is still a buyer for routing/middleware purposes.
        ->and($user->isBuyer())->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        // Buyers get no store.
        ->and(Store::where('user_id', $user->id)->exists())->toBeFalse();
});

test('admin creates a normal buyer', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Omar',
            'last_name' => 'Idrissi',
            'email' => 'omar@client.ma',
            'phone_number' => '0611223344',
            'role' => 'buyer',
        ])
        ->assertRedirect();

    $user = User::firstWhere('email', 'omar@client.ma');

    expect($user->role)->toBe('buyer')
        ->and($user->isProfessionalBuyer())->toBeFalse()
        ->and($user->isBuyer())->toBeTrue();
});

test('a buyer description is saved on the user', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Salma',
            'last_name' => 'Bennani',
            'email' => 'salma@client.ma',
            'phone_number' => '0655443322',
            'role' => 'buyer',
            'description' => 'Revendeuse à Casablanca, commande surtout des tapis.',
        ])
        ->assertRedirect();

    expect(User::firstWhere('email', 'salma@client.ma')->bio)
        ->toBe('Revendeuse à Casablanca, commande surtout des tapis.');
});

test('a seller description describes the shop, not the seller', function () {
    $category = Category::create([
        'slug' => 'tapis',
        'name' => ['fr' => 'Tapis', 'ar' => 'زرابي', 'en' => 'Rugs'],
    ]);
    $city = City::create(['name' => 'Marrakech']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Youssef',
            'last_name' => 'Amrani',
            'email' => 'youssef@artisan.ma',
            'role' => 'seller',
            'store_name' => 'Tapis Amrani',
            'category_id' => $category->id,
            'city_id' => $city->id,
            'description' => 'Atelier familial de tapis noués main.',
        ])
        ->assertRedirect();

    $user = User::firstWhere('email', 'youssef@artisan.ma');

    // The seller edits their own bio in seller settings — the admin's text
    // belongs to the store, so the user bio must stay untouched.
    expect($user->bio)->toBeNull()
        ->and(Store::firstWhere('user_id', $user->id)->description)
        ->toBe('Atelier familial de tapis noués main.');
});

test('a description at the 2500 limit is accepted', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Juste',
            'last_name' => 'Assez',
            'email' => 'juste@assez.ma',
            'role' => 'buyer',
            'description' => str_repeat('a', 2500),
        ])
        ->assertSessionHasNoErrors();
});

test('an over-long description is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Trop',
            'last_name' => 'Long',
            'email' => 'trop@long.ma',
            'role' => 'buyer',
            'description' => str_repeat('a', 2501),
        ])
        ->assertSessionHasErrors('description');
});

test('a seller cannot be created without store details', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Sans',
            'last_name' => 'Boutique',
            'email' => 'sans@boutique.ma',
            'role' => 'seller',
        ])
        ->assertSessionHasErrors(['store_name', 'category_id', 'city_id']);
});

test('an unknown role is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Faux',
            'last_name' => 'Role',
            'email' => 'faux@role.ma',
            'role' => 'super_admin',
        ])
        ->assertSessionHasErrors('role');
});

test('professional buyers reach buyer-only routes', function () {
    $pro = User::factory()->create([
        'role' => 'professional_buyer',
        'email_verified_at' => now(),
    ]);

    // role:buyer,professional_buyer must admit both buyer kinds.
    $this->actingAs($pro)->get(route('cart.show'))->assertSuccessful();
});

test('sellers are still blocked from buyer routes', function () {
    $seller = User::factory()->create([
        'role' => 'seller',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($seller)->get(route('cart.show'))->assertRoleDenied();
});

/**
 * Artisans are often signed up in person, before the CEO has their contact
 * details. The account is created anyway and completed later from the edit
 * screen — see the users.email nullable migration.
 */
test('admin creates a buyer with neither email nor phone', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Sans',
            'last_name' => 'Contact',
            'role' => 'buyer',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user = User::firstWhere('first_name', 'Sans');

    expect($user)->not->toBeNull()
        ->and($user->email)->toBeNull()
        ->and($user->phone_number)->toBeNull()
        // Nothing to pre-verify without an address on file.
        ->and($user->email_verified_at)->toBeNull();
});

test('admin creates a seller with a store but no contact details', function () {
    $category = Category::create([
        'slug' => 'tapis',
        'name' => ['fr' => 'Tapis', 'ar' => 'زربية', 'en' => 'Rugs'],
    ]);
    $city = City::create(['name' => 'Taznakht']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Fatima',
            'last_name' => 'Ait',
            'role' => 'seller',
            'store_name' => 'Tapis Taznakht',
            'category_id' => $category->id,
            'city_id' => $city->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user = User::firstWhere('first_name', 'Fatima');

    expect($user->email)->toBeNull()
        ->and(Store::firstWhere('user_id', $user->id)->store_name)
        ->toBe('Tapis Taznakht');
});

/**
 * NULL is exempt from a unique index in both MySQL and Postgres, so any
 * number of contactless accounts can coexist. Worth pinning: if the index
 * were ever rebuilt in a way that treats NULLs as equal, the second create
 * would start failing and the feature would silently break after one use.
 */
test('several users can be contactless at once', function () {
    foreach (['Un', 'Deux', 'Trois'] as $name) {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'first_name' => $name,
                'last_name' => 'Contact',
                'role' => 'buyer',
            ])
            ->assertSessionHasNoErrors();
    }

    expect(User::whereNull('email')->count())->toBe(3);
});

/**
 * Optional does not mean unvalidated: a real address still has to be a real
 * address, and still has to be unique.
 */
test('a malformed or duplicate email is still rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Mauvais',
            'last_name' => 'Email',
            'email' => 'pas-une-adresse',
            'role' => 'buyer',
        ])
        ->assertSessionHasErrors('email');

    User::factory()->create(['email' => 'pris@exemple.ma']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Doublon',
            'last_name' => 'Email',
            'email' => 'pris@exemple.ma',
            'role' => 'buyer',
        ])
        ->assertSessionHasErrors('email');
});

/**
 * Every notification in app/Notifications is mail-only, and a queued job with
 * no To address can only fail in the worker. A contactless seller reaches
 * this for real: an admin can list products on their behalf, so a buyer's
 * order would fire NewOrderReceived at an account with nowhere to send it.
 */
test('notifications are dropped for a contactless user', function () {
    Notification::fake();

    User::factory()->create(['email' => null])
        ->notify(new WelcomeUser('secret'));

    Notification::assertNothingSent();

    // ...and start flowing again as soon as an address exists, so this is a
    // deferral rather than a permanent hole.
    User::factory()->create(['email' => 'joignable@exemple.ma'])
        ->notify(new WelcomeUser('secret'));

    Notification::assertCount(1);
});
