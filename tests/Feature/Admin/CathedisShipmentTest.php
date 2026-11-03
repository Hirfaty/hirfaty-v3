<?php

use App\Models\CathedisCity;
use App\Models\CathedisSector;
use App\Models\CathedisShipment;
use App\Models\City;
use App\Models\DeliveryService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\Admin\CathedisDeliveryIssue;
use App\Notifications\OrderDelivered;
use App\Notifications\OrderShipped;
use App\Services\Cathedis\CathedisShipmentService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Shipping a purchase with Cathedis from the desk: the parcel (one for the
 * whole basket), its slip, the pickup request, and Cathedis's statuses carried
 * back onto the orders by `cathedis:refresh-deliveries`.
 */

const CATHEDIS_DELIVERY_ID = 536382;

beforeEach(function () {
    config([
        // Pinned, so a CATHEDIS_API_URL in a local .env never reaches these tests.
        'services.cathedis.url' => 'https://api.cathedis.delivery',
        'services.cathedis.username' => 'hirfaty',
        'services.cathedis.password' => 'secret',
        'services.cathedis.pickup_point_id' => null,
    ]);

    Http::preventStrayRequests();
});

/**
 * A purchase of one 2 kg piece per workshop, delivered by Cathedis to Rabat
 * (Cathedis city 3, sectors AGDAL and Autre), cash on delivery unless
 * overridden. The first order carries the 35 MAD delivery fee.
 *
 * @return array{admin: User, buyer: User, orders: list<Order>, city: City}
 */
function cathedisPurchase(array $overrides = [], int $stores = 2, bool $linked = true): array
{
    $service = DeliveryService::create(['slug' => 'cathedis', 'name' => 'Cathedis']);
    CathedisCity::create(['id' => 3, 'name' => 'Rabat', 'aliases' => [], 'delivers' => true, 'archived' => false]);
    CathedisSector::create(['id' => 232, 'cathedis_city_id' => 3, 'name' => 'AGDAL']);
    CathedisSector::create(['id' => 2873, 'cathedis_city_id' => 3, 'name' => 'Autre']);
    $city = City::create(['name' => 'Rabat', 'cathedis_city_id' => $linked ? 3 : null]);

    $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now()]);
    $group = 'HRF-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));

    $orders = [];

    foreach (range(1, $stores) as $i) {
        $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
        $store = Store::create([
            'user_id' => $seller->id,
            'store_name' => "Atelier {$i}",
            'slug' => 'atelier-'.Str::random(8),
            'status' => 'active',
        ]);
        $product = Product::create([
            'store_id' => $store->id,
            'name' => "Tajine {$i}",
            'slug' => 'tajine-'.Str::random(8),
            'base_price' => 300,
            'stock' => 5,
            'status' => 'published',
            'weight' => 2,
            'weight_unit' => 'kg',
        ]);

        $orders[] = $order = Order::create(array_merge([
            'group_reference' => $group,
            'buyer_id' => $buyer->id,
            'store_id' => $store->id,
            'city_id' => $city->id,
            'status' => 'confirmed',
            'payment_method' => Order::PAYMENT_COD,
            'payment_status' => 'pending',
            'total_amount' => 300,
            'platform_fee' => 60,
            'delivery_fee' => $i === 1 ? 35 : 0,
            'seller_earnings' => 240,
            'delivery_service_id' => $service->id,
            'delivery_service_name' => 'Cathedis',
            'shipping_address' => 'Yasmine Alaoui, 12 Rue Ibn Battouta, Rabat 10000',
            'shipping_phone' => '+212 6 12 34 56 78',
            'notes' => 'Appeler avant 18 h',
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 300,
            'subtotal' => 300,
        ]);
    }

    return ['admin' => $admin, 'buyer' => $buyer, 'orders' => $orders, 'city' => $city];
}

/**
 * Cathedis's API. `$answers` maps an action ("delivery.api.save"…) or
 * "fetch" to a closure returning the response; the login always succeeds.
 */
function fakeCathedis(array $answers = []): void
{
    Http::fake(function (HttpRequest $request) use ($answers) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/login.jsp') {
            return Http::response('', 200, ['Set-Cookie' => 'JSESSIONID=SESSION1; Path=/; HttpOnly']);
        }

        if (str_starts_with($path, '/ws/files/report/')) {
            return Http::response('%PDF-1.4 slip', 200, ['Content-Type' => 'application/pdf']);
        }

        $key = $path === '/ws/action' ? $request['action'] : (str_ends_with($path, '/fetch') ? 'fetch' : null);

        if ($key !== null && isset($answers[$key])) {
            return $answers[$key]($request);
        }

        if ($key === 'delivery.api.save') {
            return Http::response(['status' => 0, 'data' => [['values' => ['delivery' => [
                'id' => CATHEDIS_DELIVERY_ID,
                'deliveryStatus' => 'En Attente Ramassage',
                'status' => 'unpaid',
            ]]]]]);
        }

        return Http::response(['status' => -1, 'data' => ['message' => 'unexpected']], 404);
    });
}

function cathedisFetch(string $name, string $type, string $payment = 'UNPAID'): Closure
{
    return fn () => Http::response(['status' => 0, 'data' => [[
        'id' => CATHEDIS_DELIVERY_ID,
        'deliveryStatus' => ['code' => $name, 'name' => $name, 'id' => 1, 'type' => $type],
        'status' => $payment,
    ]]]);
}

/** The purchase's parcel, as if the desk had already created it. */
function cathedisShipment(Order $order): CathedisShipment
{
    $code = CathedisShipmentService::code(CATHEDIS_DELIVERY_ID);
    $order->groupOrders()->update(['tracking_number' => $code]);

    return CathedisShipment::create([
        'purchase_reference' => $order->group_reference,
        'delivery_id' => CATHEDIS_DELIVERY_ID,
        'code' => $code,
        'amount' => 635,
        'payment_type' => 'ESPECES',
        'range_weight' => 'Entre 1.2 Kg et 5 Kg',
        'delivery_status' => 'En Attente Ramassage',
        'delivery_status_type' => 'PENDING',
    ]);
}

/** The draft the order page offers for the purchase. */
function cathedisDraft(Order $order): array
{
    return app(CathedisShipmentService::class)->draft($order->groupOrders()->orderBy('id')->get());
}

/** Send the dialog: the draft as offered, with the desk's `$edits`. */
function shipWithCathedis($test, User $admin, Order $order, array $edits = [])
{
    return $test->actingAs($admin)
        ->from(route('admin.orders.show', $order->reference))
        ->post(route('admin.orders.cathedis.store', $order->reference), array_merge(cathedisDraft($order), $edits));
}

it('offers the parcel as it would be sent, for the desk to check', function () {
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase();

    $this->actingAs($admin)->get(route('admin.orders.show', $orders[0]->reference))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cathedis.blocked', null)
            ->where('cathedis.warnings', [])
            ->where('cathedis.draft.recipient', 'Yasmine Alaoui')
            ->where('cathedis.draft.address', '12 Rue Ibn Battouta, Rabat 10000')
            ->where('cathedis.draft.phone', '0612345678')
            ->where('cathedis.draft.cathedis_city_id', 3)
            ->where('cathedis.draft.sector', 'Autre')
            ->where('cathedis.draft.amount', 635)
            ->where('cathedis.draft.payment_type', 'ESPECES')
            ->where('cathedis.draft.range_weight', 'Entre 1.2 Kg et 5 Kg')
            ->where('cathedis.draft.comment', 'Appeler avant 18 h')
            ->where('cathedis.draft.remember_city', false)
            ->where('cathedis.ranges', CathedisShipmentService::RANGES));
});

it('creates one parcel for the whole purchase and gives every order its code', function () {
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase();
    fakeCathedis();

    shipWithCathedis($this, $admin, $orders[0], ['comment' => 'Fragile, poterie'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Colis Cathedis créé : LD000536382.');

    $shipment = CathedisShipment::sole();

    expect($shipment->purchase_reference)->toBe($orders[0]->group_reference)
        ->and((float) $shipment->amount)->toBe(635.0)
        ->and($shipment->payment_type)->toBe('ESPECES')
        ->and($shipment->delivery_status)->toBe('En Attente Ramassage')
        // Creating the parcel moves nothing: Cathedis has not collected it yet.
        ->and(Order::pluck('status')->unique()->all())->toBe(['confirmed'])
        ->and(Order::pluck('tracking_number')->unique()->all())->toBe(['LD000536382']);

    Http::assertSent(fn (HttpRequest $request) => ($request->data()['action'] ?? null) === 'delivery.api.save'
        && $request->hasHeader('Cookie', 'JSESSIONID=SESSION1')
        && $request['data']['context']['delivery'] == [
            'recipient' => 'Yasmine Alaoui',
            'address' => '12 Rue Ibn Battouta, Rabat 10000',
            'phone' => '0612345678',
            'city' => 'Rabat',
            'sector' => 'Autre',
            'amount' => '635.00',
            'caution' => '0',
            'fragile' => '0',
            'declaredValue' => '0',
            'nomOrder' => $orders[0]->group_reference,
            'comment' => 'Fragile, poterie',
            'rangeWeight' => 'Entre 1.2 Kg et 5 Kg',
            'subject' => 'Tajine 1, Tajine 2',
            'paymentType' => 'ESPECES',
            'deliveryType' => 'Livraison CRBT',
            'packageCount' => '1',
            'allowOpening' => '0',
        ]);
});

it('ships a purchase paid online with nothing to collect', function () {
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase([
        'payment_method' => Order::PAYMENT_CARD,
        'payment_status' => 'paid',
    ], stores: 1);
    fakeCathedis();

    shipWithCathedis($this, $admin, $orders[0], ['fragile' => true, 'allow_opening' => true, 'package_count' => 2])
        ->assertSessionHas('success');

    Http::assertSent(fn (HttpRequest $request) => ($request->data()['action'] ?? null) === 'delivery.api.save'
        && $request['data']['context']['delivery']['amount'] === '0.00'
        && $request['data']['context']['delivery']['paymentType'] === 'PAYE'
        && $request['data']['context']['delivery']['fragile'] === '1'
        && $request['data']['context']['delivery']['declaredValue'] === '300.00'
        && $request['data']['context']['delivery']['allowOpening'] === '1'
        && $request['data']['context']['delivery']['packageCount'] === '2');
});

it('never creates a second parcel for the same purchase', function () {
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase();
    fakeCathedis();

    shipWithCathedis($this, $admin, $orders[0])->assertSessionHas('success');
    shipWithCathedis($this, $admin, $orders[1])
        ->assertSessionHas('error', 'Cathedis : Un colis Cathedis existe déjà pour cet achat (LD000536382).');

    expect(CathedisShipment::count())->toBe(1);
    Http::assertSentCount(2); // one login, one delivery
});

it('tells the desk why a purchase cannot ship at all, without calling Cathedis', function (array $setup, string $reason) {
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase($setup['overrides'] ?? []);

    if ($setup['unconfigured'] ?? false) {
        config(['services.cathedis.password' => null]);
    }

    fakeCathedis();

    $this->actingAs($admin)->get(route('admin.orders.show', $orders[0]->reference))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cathedis.shipment', null)
            ->where('cathedis.draft', null)
            ->where('cathedis.blocked', fn (string $blocked) => str_contains($blocked, $reason)));

    shipWithCathedis($this, $admin, $orders[0])->assertSessionHas('error', fn (string $error) => str_contains($error, $reason));

    expect(CathedisShipment::count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'login not set' => [['unconfigured' => true], 'Identifiants Cathedis'],
    'card payment not settled' => [['overrides' => ['payment_method' => Order::PAYMENT_CARD, 'payment_status' => 'refunded']], 'pas réglé'],
]);

it('points the desk at what to correct before sending', function (array $setup, string $warning) {
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase($setup['overrides'] ?? [], linked: $setup['linked'] ?? true);

    $this->actingAs($admin)->get(route('admin.orders.show', $orders[0]->reference))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cathedis.blocked', null)
            ->where('cathedis.warnings', fn ($warnings) => collect($warnings)->contains(fn (string $w) => str_contains($w, $warning))));
})->with([
    'city not linked' => [['linked' => false], 'n\'est pas reliée à une ville Cathedis'],
    'phone Cathedis refuses' => [['overrides' => ['shipping_phone' => '+33 6 12 34 56 78']], 'n\'est pas accepté par Cathedis'],
]);

it('sends the parcel as the desk corrected it', function () {
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase(['shipping_phone' => '+33 6 12 34 56 78'], stores: 1);
    fakeCathedis();

    shipWithCathedis($this, $admin, $orders[0], [
        'recipient' => 'Karim Alaoui',
        'phone' => '07 12 34 56 78',
        'sector' => 'AGDAL',
        'amount' => 250,
        'subject' => 'Tajine en terre cuite',
        'range_weight' => 'Plus de 30Kg',
        'weight' => 42,
    ])->assertSessionHas('success');

    Http::assertSent(fn (HttpRequest $request) => ($request->data()['action'] ?? null) === 'delivery.api.save'
        && $request['data']['context']['delivery']['recipient'] === 'Karim Alaoui'
        && $request['data']['context']['delivery']['phone'] === '0712345678'
        && $request['data']['context']['delivery']['sector'] === 'AGDAL'
        && $request['data']['context']['delivery']['amount'] === '250.00'
        && $request['data']['context']['delivery']['subject'] === 'Tajine en terre cuite'
        && $request['data']['context']['delivery']['rangeWeight'] === 'Plus de 30Kg'
        && $request['data']['context']['delivery']['weight'] === '42');
});

it('lets the desk pick the Cathedis city of an unlinked locality, and keeps it', function () {
    ['admin' => $admin, 'orders' => $orders, 'city' => $city] = cathedisPurchase(linked: false, stores: 1);
    CathedisCity::create(['id' => 762, 'name' => 'Akka', 'aliases' => [], 'delivers' => true, 'archived' => false]);
    CathedisSector::create(['id' => 3569, 'cathedis_city_id' => 762, 'name' => 'Autre']);
    fakeCathedis();

    expect(cathedisDraft($orders[0]))->toMatchArray(['cathedis_city_id' => null, 'sector' => null, 'remember_city' => true]);

    $this->actingAs($admin)->getJson(route('admin.cathedis.cities'))
        ->assertOk()
        ->assertJsonFragment(['id' => 762, 'name' => 'Akka', 'default_sector' => 'Autre']);

    shipWithCathedis($this, $admin, $orders[0], ['cathedis_city_id' => 762, 'sector' => 'Autre'])
        ->assertSessionHas('success');

    Http::assertSent(fn (HttpRequest $request) => ($request->data()['action'] ?? null) === 'delivery.api.save'
        && $request['data']['context']['delivery']['city'] === 'Akka');

    expect($city->fresh()->cathedis_city_id)->toBe(762);
});

it('refuses edits Cathedis would reject, without calling it', function (array $edits, string $error) {
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase(stores: 1);
    CathedisCity::create(['id' => 65, 'name' => 'Talmest-SAFI RG-', 'aliases' => [], 'delivers' => false, 'archived' => false]);
    fakeCathedis();

    shipWithCathedis($this, $admin, $orders[0], $edits)
        ->assertSessionHas('error', fn (string $message) => str_contains($message, $error));

    expect(CathedisShipment::count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'sector of another city' => [['sector' => 'Maarif'], 'n\'existe pas à Rabat'],
    'city Cathedis does not serve' => [['cathedis_city_id' => 65, 'sector' => 'Autre'], 'Choisissez une ville que Cathedis dessert'],
    'phone still wrong' => [['phone' => '+33 6 12 34 56 78'], 'Numéro du destinataire refusé'],
    'fragile under 250 MAD' => [['fragile' => true, 'declared_value' => 200], 'plus de 250 MAD'],
]);

it('needs the weight above 30 kg', function () {
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase(stores: 1);
    fakeCathedis();

    shipWithCathedis($this, $admin, $orders[0], ['range_weight' => 'Plus de 30Kg', 'weight' => null])
        ->assertSessionHasErrors(['weight' => 'Au-delà de 30 kg, indiquez le poids exact.']);

    Http::assertNothingSent();
});

it('hides the panel for a purchase another carrier ships', function () {
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase(stores: 1);
    $dhl = DeliveryService::create(['slug' => 'dhl', 'name' => 'DHL Express']);
    Order::query()->update(['delivery_service_id' => $dhl->id]);

    $this->actingAs($admin)->get(route('admin.orders.show', $orders[0]->reference))
        ->assertInertia(fn (Assert $page) => $page->where('cathedis', null));
});

it('leaves the purchase unshipped when Cathedis refuses the delivery', function () {
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase();
    fakeCathedis(['delivery.api.save' => fn () => Http::response(['status' => -1, 'data' => ['message' => 'Secteur introuvable']])]);

    shipWithCathedis($this, $admin, $orders[0])
        ->assertSessionHas('error', fn (string $error) => str_contains($error, 'Secteur introuvable'));

    expect(CathedisShipment::count())->toBe(0)
        ->and(Order::whereNotNull('tracking_number')->count())->toBe(0);
});

it('logs in again once when Cathedis no longer accepts the session', function () {
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase(stores: 1);
    $calls = 0;
    fakeCathedis(['delivery.api.save' => function () use (&$calls) {
        return ++$calls === 1
            ? Http::response('', 401)
            : Http::response(['status' => 0, 'data' => [['values' => ['delivery' => ['id' => CATHEDIS_DELIVERY_ID]]]]]);
    }]);

    shipWithCathedis($this, $admin, $orders[0])->assertSessionHas('success');

    expect($calls)->toBe(2);
    Http::assertSentCount(4); // login, refused save, login, save
});

it('downloads the slip as a PDF', function () {
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase(stores: 1);
    cathedisShipment($orders[0]);
    fakeCathedis(['delivery.print.bl4x4' => fn () => Http::response(['status' => 0, 'data' => [[
        'view' => ['views' => [['name' => 'ws/files/report/8256746505489084573?name=Bon+de+livraison.pdf', 'type' => 'html']]],
    ]]])]);

    $this->actingAs($admin)
        ->get(route('admin.orders.cathedis.label', ['order' => $orders[0]->reference, 'format' => '10x10']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertSee('%PDF-1.4 slip', false);

    Http::assertSent(fn (HttpRequest $request) => ($request->data()['action'] ?? null) === 'delivery.print.bl4x4'
        && $request['data']['context'] === ['_ids' => [CATHEDIS_DELIVERY_ID], '_model' => 'com.tracker.delivery.db.Delivery']);
});

it('asks Cathedis to collect the parcels waiting for pickup', function () {
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase(stores: 1);
    $shipment = cathedisShipment($orders[0]);
    fakeCathedis(['action-refresh-pickup-request' => fn () => Http::response(['status' => 0, 'data' => [['values' => ['pickupRequest' => [
        'name' => 'DR1758170122-34092',
        'agent' => ['fullName' => 'Salah Ouahim'],
        'deliveriesCount' => 1,
    ]]]]])]);

    $this->actingAs($admin)->from(route('admin.orders.show', $orders[0]->reference))
        ->post(route('admin.cathedis.pickup'))
        ->assertSessionHas('success', 'Ramassage demandé pour 1 colis — ramasseur : Salah Ouahim (DR1758170122-34092).');

    expect($shipment->fresh()->pickup_requested_at)->not->toBeNull();
});

it('carries Cathedis statuses onto the purchase', function () {
    Notification::fake();
    ['buyer' => $buyer, 'orders' => $orders] = cathedisPurchase();
    $shipment = cathedisShipment($orders[0]);

    $answers = [
        cathedisFetch('Collecté', 'PENDING'),
        cathedisFetch('Collecté', 'PENDING'),
        cathedisFetch('Mise En Distribution', 'PENDING'),
        cathedisFetch('Livré', 'SHIPPED', 'PAID'),
    ];
    fakeCathedis(['fetch' => function () use (&$answers) {
        return array_shift($answers)();
    }]);

    // Collected: the purchase is shipped, and the buyer told once.
    $this->artisan('cathedis:refresh-deliveries')->assertSuccessful();
    expect(Order::pluck('status')->unique()->all())->toBe(['shipped']);
    Notification::assertSentToTimes($buyer, OrderShipped::class, 1);

    // The same status again writes nothing new.
    $this->artisan('cathedis:refresh-deliveries')->assertSuccessful();
    expect($orders[0]->statusEvents()->whereNotNull('note')->count())->toBe(1);

    // Out for delivery: a line on the tracking page, no email.
    $this->artisan('cathedis:refresh-deliveries')->assertSuccessful();
    expect($orders[0]->statusEvents()->get()->last()->note)->toBe('Colis en cours de livraison : le livreur va vous appeler.');

    // Delivered: the purchase is delivered and the parcel no longer polled.
    $this->artisan('cathedis:refresh-deliveries')->assertSuccessful();
    expect(Order::pluck('status')->unique()->all())->toBe(['delivered'])
        ->and($shipment->fresh()->closed_at)->not->toBeNull()
        ->and($shipment->fresh()->payment_status)->toBe('paid');
    Notification::assertSentToTimes($buyer, OrderDelivered::class, 1);

    $this->artisan('cathedis:refresh-deliveries')->expectsOutputToContain('0 Cathedis parcel(s) checked')->assertSuccessful();
});

it('alerts the desk when a parcel is refused, without cancelling anything', function () {
    Notification::fake();
    ['admin' => $admin, 'orders' => $orders] = cathedisPurchase(stores: 1);
    cathedisShipment($orders[0]);
    fakeCathedis(['fetch' => cathedisFetch('Annulé Au Téléphone', 'REJECTED')]);

    $this->artisan('cathedis:refresh-deliveries')->assertSuccessful();

    expect($orders[0]->fresh()->status)->toBe('confirmed');
    Notification::assertSentTo($admin, CathedisDeliveryIssue::class, fn (CathedisDeliveryIssue $issue) => $issue->status === 'Annulé Au Téléphone'
        && $issue->code === 'LD000536382');
});

it('keeps Cathedis for the desk', function () {
    ['buyer' => $buyer, 'orders' => $orders] = cathedisPurchase(stores: 1);

    $this->actingAs($buyer)->post(route('admin.orders.cathedis.store', $orders[0]->reference))->assertRoleDenied();
    $this->actingAs($buyer)->post(route('admin.cathedis.pickup'))->assertRoleDenied();
});

it('reads a Moroccan phone number the way Cathedis wants it', function (?string $typed, ?string $sent) {
    expect(CathedisShipmentService::phone($typed))->toBe($sent);
})->with([
    ['0612345678', '0612345678'],
    ['06 12 34 56 78', '0612345678'],
    ['+212 6 12 34 56 78', '0612345678'],
    ['00212712345678', '0712345678'],
    ['212522123456', '0522123456'],
    ['+33612345678', null],
    ['0412345678', null],
    [null, null],
]);
