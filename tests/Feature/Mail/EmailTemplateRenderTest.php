<?php

/*
 * Every transactional email must render, and must render as *email* HTML.
 *
 * The rules below are not stylistic. Each one is a construct that silently
 * broke a template in production:
 *
 *  - `display:flex` / `gap` — unsupported by Outlook (which renders mail
 *    through Word) and by Gmail. Every label/value row and every numbered
 *    step built with flexbox collapsed into one run of text.
 *  - `rgba()` — unsupported by the same Word renderer, so tinted panels and
 *    hairlines simply disappeared there.
 *  - a <style> block carrying the layout — Gmail strips <style> in several
 *    ordinary cases (forwarded mail, the mobile app on a non-Gmail account),
 *    and a class-based layout then arrives as unstyled text.
 *
 * Asserting on rendered output rather than on the Blade source, because a
 * component could reintroduce any of them on every template at once.
 */

use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** A seller, their shop and one published product. */
function mailFixture(): array
{
    $seller = User::factory()->create(['role' => 'seller', 'first_name' => 'Amina']);
    $buyer = User::factory()->create(['role' => 'buyer', 'first_name' => 'Youssef']);

    $category = Category::firstOrCreate(
        ['slug' => 'mail-craft'],
        ['name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery']],
    );
    $city = City::firstOrCreate(['name' => 'Safi']);

    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Safi',
        'slug' => 'atelier-safi-'.Str::random(6),
        'type' => 'artist',
        'category_id' => $category->id,
        'city_id' => $city->id,
        'description' => 'Une présentation suffisamment longue.',
        'status' => 'active',
    ]);

    $product = Product::create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'name' => 'Tajine émaillé',
        'slug' => Str::slug('tajine-'.Str::random(6)),
        'base_price' => 240,
        'sale_price' => 190,
        'stock' => 5,
        'status' => 'published',
    ]);

    $order = Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $store->id,
        'city_id' => $city->id,
        'reference' => 'HRF-'.Str::upper(Str::random(8)),
        'group_reference' => 'HRFG-'.Str::upper(Str::random(8)),
        'status' => 'pending',
        'total_amount' => 380,
        'platform_fee' => 76,
        'delivery_fee' => 20,
        'seller_earnings' => 324,
        'shipping_address' => '12 rue des Potiers, Safi',
        'shipping_phone' => '0600000000',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 190,
        'subtotal' => 380,
    ]);

    $order->load(['store.city', 'store.user', 'buyer', 'items.product', 'city']);

    return compact('seller', 'buyer', 'store', 'product', 'order');
}

/**
 * Every email view, with the data its notification passes it.
 *
 * @return array<string, array{0: string, 1: callable}>
 */
function mailViews(): array
{
    $locales = ['fr', 'en', 'ar'];
    $views = [];

    foreach ($locales as $locale) {
        $views["seller.product.approved.{$locale}"] = fn (array $f) => [
            'product' => $f['product'], 'user' => $f['seller'],
        ];
        $views["seller.product.rejected.{$locale}"] = fn (array $f) => [
            'product' => $f['product'], 'user' => $f['seller'],
            'reason' => 'Les photos ne montrent pas vos propres créations.',
        ];
        $views["seller.order.new.{$locale}"] = fn (array $f) => [
            'order' => $f['order'], 'user' => $f['seller'],
        ];
        $views["seller.order.cancelled-by-buyer.{$locale}"] = fn (array $f) => [
            'order' => $f['order'], 'user' => $f['seller'],
        ];
        $views["buyer.order-placed.{$locale}"] = fn (array $f) => [
            'orders' => collect([$f['order']]),
            'reference' => $f['order']->group_reference,
            'user' => $f['buyer'],
        ];

        foreach (['confirmed', 'shipped', 'delivered', 'cancelled'] as $event) {
            $views["buyer.order-status.{$event}.{$locale}"] = fn (array $f) => [
                'orders' => collect([$f['order']]),
                'reference' => $f['order']->group_reference,
                'user' => $f['buyer'],
            ];
        }

        $views["buyer.professional.approved.{$locale}"] = fn (array $f) => ['user' => $f['buyer']];
        $views["buyer.professional.declined.{$locale}"] = fn (array $f) => [
            'user' => $f['buyer'], 'reason' => 'Établissement non confirmé.',
        ];
        $views["auth.reset-password.{$locale}"] = fn (array $f) => [
            'user' => $f['buyer'], 'url' => 'https://hirfaty.com/reset/token',
            'count' => 60,
        ];
    }

    $views['admin.purchase.new'] = fn (array $f) => [
        'orders' => collect([$f['order']]),
        'buyer' => $f['buyer'],
        'buyerName' => 'Youssef Alami',
        'reference' => $f['order']->group_reference,
    ];

    foreach ($locales as $locale) {
        $views["verify:{$locale}"] = fn (array $f) => [
            '__view' => 'emails.verify',
            'user' => $f['buyer'], 'url' => 'https://hirfaty.com/verify/1/abc',
            'locale' => $locale, 'audience' => 'buyer', 'expiresInMinutes' => 60,
        ];
        $views["auth-code:{$locale}"] = fn (array $f) => [
            '__view' => 'emails.auth-code',
            'code' => '480913', 'locale' => $locale,
            'expiresInMinutes' => 15, 'copy' => 'login_code',
        ];
    }

    return $views;
}

test('every email template renders without email-hostile CSS', function () {
    $fixture = mailFixture();
    $failures = [];

    foreach (mailViews() as $name => $dataFn) {
        $data = $dataFn($fixture);
        $view = $data['__view'] ?? 'emails.'.$name;
        unset($data['__view']);

        try {
            $html = view($view, $data)->render();
        } catch (Throwable $e) {
            $failures[] = "{$name}: did not render — ".$e->getMessage();

            continue;
        }

        // Flexbox: Outlook and Gmail ignore it, collapsing rows and steps.
        if (preg_match('/display\s*:\s*flex/i', $html)) {
            $failures[] = "{$name}: uses display:flex";
        }

        // `gap` only works alongside flex/grid; same clients, same failure.
        if (preg_match('/[^-a-z]gap\s*:/i', $html)) {
            $failures[] = "{$name}: uses gap";
        }

        // rgba() is dropped by Outlook's Word renderer.
        if (str_contains($html, 'rgba(')) {
            $failures[] = "{$name}: uses rgba()";
        }

        // A <style> block means the layout dies wherever Gmail strips it.
        if (preg_match('/<style[\s>]/i', $html)) {
            $failures[] = "{$name}: ships a <style> block";
        }

        // The positive half: it has to actually be a table layout.
        if (! str_contains($html, '<table')) {
            $failures[] = "{$name}: is not table-based";
        }

        // A stray Blade component tag means a typo'd component name was
        // emitted as literal text instead of being compiled.
        if (str_contains($html, '<x-')) {
            $failures[] = "{$name}: leaked an uncompiled <x- component tag";
        }
    }

    // Joined rather than compared as an array: a 100-entry array diff is
    // unreadable, and this is a list meant to be worked through.
    expect(implode("\n", $failures))->toBe('');
});
