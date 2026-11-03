<?php

use App\Models\BrochureLead;
use App\Models\Category;
use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * The "Exporter" button on the brochure, newsletter, products, orders,
 * workshops and delivery-rates lists. The
 * file mechanics (BOM, formula neutralising, xlsx typing) are pinned in
 * AdminUsersExportTest; this covers what each list puts in its file.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);
});

/** @return list<list<string>> */
function listExportCsv($response): array
{
    $body = $response->assertOk()->streamedContent();

    return array_map(
        fn ($line) => str_getcsv($line, escape: ''),
        preg_split('/\R/', trim(substr($body, 3))),
    );
}

// ── Brochure ────────────────────────────────────────────────────────────────

test('the brochure export lists the leads under the active type filter', function () {
    BrochureLead::create(['first_name' => 'Fatima', 'last_name' => 'Alaoui', 'email' => 'fatima@example.com', 'type' => 'seller', 'seller_type' => 'cooperative', 'locale' => 'ar', 'downloads_count' => 2]);
    BrochureLead::create(['first_name' => 'Karim', 'last_name' => 'Idrissi', 'email' => 'karim@example.com', 'type' => 'buyer', 'locale' => 'fr', 'downloads_count' => 1]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.brochure-leads.export', ['format' => 'csv', 'type' => 'seller']))
        ->assertDownload('brochure-hirfaty-'.now()->format('Y-m-d').'.csv');

    $rows = listExportCsv($response);

    expect($rows)->toHaveCount(2)
        ->and($rows[1])->toContain('Fatima', 'fatima@example.com', 'Vendeur', 'Coopérative', 'ar', '2');
});

// ── Newsletter ──────────────────────────────────────────────────────────────

test('the newsletter export lists the subscribers matching the search', function () {
    NewsletterSubscriber::create(['email' => 'amina@example.com', 'locale' => 'fr']);
    NewsletterSubscriber::create(['email' => 'youssef@example.com', 'locale' => 'en']);

    $rows = listExportCsv(
        $this->actingAs($this->admin)
            ->get(route('admin.newsletter-subscribers.export', ['format' => 'csv', 'search' => 'amina']))
    );

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['ID', 'Email', 'Langue', 'Inscrit le'])
        ->and($rows[1][1])->toBe('amina@example.com');
});

test('the newsletter export also comes as excel', function () {
    NewsletterSubscriber::create(['email' => 'amina@example.com', 'locale' => 'fr']);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.newsletter-subscribers.export', ['format' => 'xlsx']))
        ->assertOk()
        ->assertDownload('newsletter-hirfaty-'.now()->format('Y-m-d').'.xlsx');

    $reader = new XlsxReader;
    $reader->open($response->getFile()->getPathname());
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();

    expect($rows)->toHaveCount(2)->and($rows[1][1])->toBe('amina@example.com');
});

// ── Products ────────────────────────────────────────────────────────────────

test('the products export lists the catalogue under the active filters', function () {
    $category = Category::create(['slug' => 'poterie', 'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery']]);
    $store = Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => $category->id,
        'city_id' => City::create(['name' => 'Fès'])->id,
    ]);

    Product::create(['store_id' => $store->id, 'category_id' => $category->id, 'name' => 'Tajine bleu', 'slug' => 'tajine-bleu', 'base_price' => 250, 'stock' => 4, 'status' => 'published']);
    Product::create(['store_id' => $store->id, 'category_id' => $category->id, 'name' => 'Vase brut', 'slug' => 'vase-brut', 'base_price' => null, 'stock' => 0, 'status' => 'draft']);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.products.export', ['format' => 'csv', 'status' => 'published']))
        ->assertDownload('produits-hirfaty-'.now()->format('Y-m-d').'.csv');

    $rows = listExportCsv($response);

    expect($rows)->toHaveCount(2)
        ->and($rows[1])->toContain('Tajine bleu', 'Atelier Fès', 'Poterie', 'Publié', '250', '4')
        ->and(end($rows[1]))->toContain('/products/tajine-bleu');

    // An unpriced draft exports a blank price, not 0.
    $all = listExportCsv($this->get(route('admin.products.export', ['format' => 'csv'])));
    $draft = collect($all)->firstWhere(1, 'Vase brut');
    expect($draft[6])->toBe('')
        ->and(end($draft))->toBe('');
});

// ── Orders ──────────────────────────────────────────────────────────────────

/** A workshop with one seller, for the orders and stores exports. */
function exportStore(string $name = 'Atelier Fès'): Store
{
    $category = Category::firstOrCreate(['slug' => 'poterie'], ['name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery']]);

    return Store::create([
        'user_id' => User::factory()->create(['role' => 'seller', 'first_name' => 'Hassan', 'last_name' => 'Tazi'])->id,
        'store_name' => $name,
        'slug' => str($name)->slug(),
        'category_id' => $category->id,
        'city_id' => City::firstOrCreate(['name' => 'Fès'])->id,
        'description' => 'Poterie de Fès.',
    ]);
}

test('the orders export has one row per parcel, with what the buyer paid and the items', function () {
    $store = exportStore();
    $buyer = User::factory()->create(['role' => 'buyer', 'first_name' => 'Salma', 'last_name' => 'Bennani']);
    $product = Product::create(['store_id' => $store->id, 'name' => 'Tajine bleu', 'slug' => 'tajine-bleu', 'base_price' => 250, 'stock' => 4, 'status' => 'published']);

    $order = Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $store->id,
        'city_id' => $store->city_id,
        'status' => 'shipped',
        'tracking_number' => 'AM123456MA',
        'total_amount' => 500,
        'platform_fee' => 100,
        'delivery_fee' => 40,
        'seller_earnings' => 400,
        'shipping_address' => '12 rue des Potiers',
        'shipping_phone' => '+212 600000000',
    ]);
    OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'variant_label' => 'Grand', 'quantity' => 2, 'unit_price' => 250, 'subtotal' => 500]);

    Order::create(['buyer_id' => $buyer->id, 'store_id' => $store->id, 'status' => 'cancelled', 'total_amount' => 90, 'platform_fee' => 18, 'delivery_fee' => 0, 'seller_earnings' => 72, 'shipping_address' => 'x']);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.orders.export', ['format' => 'csv', 'status' => 'shipped']))
        ->assertDownload('commandes-hirfaty-'.now()->format('Y-m-d').'.csv');

    $rows = listExportCsv($response);

    expect($rows)->toHaveCount(2)
        ->and($rows[1][1])->toBe($order->fresh()->reference)
        ->and($rows[1])->toContain('Expédiée', 'Salma Bennani', '+212 600000000', 'Atelier Fès', '2 × Tajine bleu (Grand)', '500', '40', 'AM123456MA')
        // Hirfaty's commission and the artisan's share stay out of the file.
        // (one value per call: a negated multi-value toContain() passes as soon
        // as any one of them is missing)
        ->and($rows[0])->not->toContain('Commission Hirfaty (MAD)')
        ->and($rows[0])->not->toContain('Part artisan (MAD)')
        ->and($rows[1])->not->toContain('100')
        ->and($rows[1])->not->toContain('400')
        ->and($rows[1])->toHaveCount(count($rows[0]));
});

// ── Stores ──────────────────────────────────────────────────────────────────

test('the workshops export lists shops with their owner, under the status tab', function () {
    $approved = exportStore('Atelier Fès');
    $approved->update(['verification_status' => Store::VERIFICATION_APPROVED, 'status' => 'active']);
    exportStore('Coopérative Safi');

    $response = $this->actingAs($this->admin)
        ->get(route('admin.stores.export', ['format' => 'csv', 'status' => 'approved']))
        ->assertDownload('ateliers-hirfaty-'.now()->format('Y-m-d').'.csv');

    $rows = listExportCsv($response);

    expect($rows)->toHaveCount(2)
        ->and($rows[1])->toContain('Atelier Fès', 'Poterie', 'Fès', 'Hassan Tazi', 'Vérifié', 'Oui')
        ->and(end($rows[1]))->toContain('/stores/atelier-fes');
});

test('the workshops export follows the category filter', function () {
    exportStore('Atelier Fès');
    $leather = Category::create(['slug' => 'cuir', 'name' => ['fr' => 'Cuir', 'ar' => 'جلد', 'en' => 'Leather']]);
    exportStore('Tannerie Safi')->update(['category_id' => $leather->id]);

    $rows = listExportCsv(
        $this->actingAs($this->admin)
            ->get(route('admin.stores.export', ['format' => 'csv', 'category_id' => $leather->id]))
    );

    expect($rows)->toHaveCount(2)
        ->and($rows[1][1])->toBe('Tannerie Safi');
});

// ── Delivery rates ──────────────────────────────────────────────────────────

test('the delivery rates export is the rate card, cities and countries alike', function () {
    $service = DeliveryService::create(['slug' => 'amana', 'name' => 'Amana', 'sort_order' => 1]);
    DeliveryRate::create(['delivery_service_id' => $service->id, 'country_code' => 'MA', 'country_name' => 'Maroc', 'city_id' => City::create(['name' => 'Casablanca', 'region' => 'Casablanca-Settat'])->id, 'fee' => 40]);
    DeliveryRate::create(['delivery_service_id' => $service->id, 'country_code' => 'FR', 'country_name' => 'France', 'city_id' => null, 'fee' => 120]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.delivery-rates.export', ['format' => 'csv', 'service' => 'amana']))
        ->assertDownload('tarifs-livraison-hirfaty-'.now()->format('Y-m-d').'.csv');

    expect(listExportCsv($response))->toBe([
        ['Transporteur', 'Code pays', 'Pays', 'Ville', 'Région', 'Zone', 'Tarif (MAD)'],
        ['Amana', 'FR', 'France', '', '', '', '120'],
        ['Amana', 'MA', 'Maroc', 'Casablanca', 'Casablanca-Settat', '', '40'],
    ]);
});

test('a weight-priced card exports each country’s zone and no fee of its own', function () {
    $dhl = DeliveryService::create(['slug' => 'dhl', 'name' => 'DHL Express', 'pricing' => DeliveryService::PRICING_WEIGHT, 'sort_order' => 2]);
    DeliveryRate::create(['delivery_service_id' => $dhl->id, 'country_code' => 'FR', 'country_name' => 'France', 'city_id' => null, 'zone' => 1, 'fee' => null]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.delivery-rates.export', ['format' => 'csv', 'service' => 'dhl']));

    expect(listExportCsv($response))->toBe([
        ['Transporteur', 'Code pays', 'Pays', 'Ville', 'Région', 'Zone', 'Tarif (MAD)'],
        ['DHL Express', 'FR', 'France', '', '', '1', ''],
    ]);
});

// ── Access ──────────────────────────────────────────────────────────────────

test('only admins can export these lists', function (string $route) {
    foreach (['seller', 'buyer'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route($route, ['format' => 'csv']))
            ->assertRoleDenied();
    }
})->with([
    'admin.brochure-leads.export',
    'admin.newsletter-subscribers.export',
    'admin.products.export',
    'admin.orders.export',
    'admin.stores.export',
    'admin.delivery-rates.export',
]);

test('an unknown format is refused', function (string $route) {
    $this->actingAs($this->admin)
        ->get(route($route, ['format' => 'pdf']))
        ->assertSessionHasErrors('format');
})->with([
    'admin.brochure-leads.export',
    'admin.newsletter-subscribers.export',
    'admin.products.export',
    'admin.orders.export',
    'admin.stores.export',
    'admin.delivery-rates.export',
]);
