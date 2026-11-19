<?php

use App\Models\DeliveryService;
use App\Models\DhlShipment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\Admin\DhlDeliveryIssue;
use App\Notifications\OrderDelivered;
use App\Notifications\OrderShipped;
use App\Services\DhlExpress\DhlShipmentService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Shipping a purchase abroad with DHL Express from the desk: the shipment
 * (one for the whole basket) with its label and customs invoice, the courier
 * booking, and DHL's checkpoints carried back onto the orders by
 * `dhl:refresh-shipments`.
 */

const DHL_AWB = '1234567890';

beforeEach(function () {
    config([
        // Pinned, so a DHL_EXPRESS_* in a local .env never reaches these tests.
        'services.dhl_express.url' => 'https://express.api.dhl.com/mydhlapi/test',
        'services.dhl_express.username' => 'hirfatyMA',
        'services.dhl_express.password' => 'secret',
        'services.dhl_express.account_number' => '950000000',
        'services.dhl_express.product_code' => 'P',
        'services.dhl_express.shipper' => [
            'company' => 'Hirfaty',
            'name' => 'Desk Hirfaty',
            'phone' => '+212522000000',
            'email' => 'desk@hirfaty.test',
            'address' => '10 Rue des Artisans',
            'city' => 'Casablanca',
            'postal_code' => '20000',
            'country' => 'MA',
        ],
    ]);

    Http::preventStrayRequests();
});

/**
 * A purchase of one 2 kg ceramic piece per workshop, paid by card and
 * shipped by DHL to Paris.
 *
 * @return array{admin: User, buyer: User, orders: list<Order>}
 */
function dhlPurchase(array $overrides = [], int $stores = 2): array
{
    $service = DeliveryService::create(['slug' => 'dhl', 'name' => 'DHL Express', 'default_piece_weight' => 1]);
    $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
    $buyer = User::factory()->create(['role' => 'buyer', 'email_verified_at' => now(), 'email' => 'claire@example.fr']);
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
            'name' => "Plat céramique {$i}",
            'slug' => 'plat-'.Str::random(8),
            'base_price' => 400,
            'stock' => 5,
            'status' => 'published',
            'weight' => 2,
            'weight_unit' => 'kg',
            'length' => 40,
            'width' => 40,
            'height' => 10,
            'hs_code' => '691200',
            'customs_description' => 'Handmade ceramic plate',
        ]);

        $orders[] = $order = Order::create(array_merge([
            'group_reference' => $group,
            'buyer_id' => $buyer->id,
            'store_id' => $store->id,
            'city_id' => null,
            'status' => 'confirmed',
            'payment_method' => Order::PAYMENT_CARD,
            'payment_status' => 'paid',
            'total_amount' => 400,
            'platform_fee' => 80,
            'delivery_fee' => $i === 1 ? 600 : 0,
            'seller_earnings' => 320,
            'delivery_service_id' => $service->id,
            'delivery_service_name' => 'DHL Express',
            'shipping_address' => 'Claire Martin, 10 Rue de Rivoli, Paris, 75001',
            'shipping_phone' => '+33 6 12 34 56 78',
            'shipping_country' => 'FR',
            'shipping_postal_code' => '75001',
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 400,
            'subtotal' => 400,
        ]);
    }

    return ['admin' => $admin, 'buyer' => $buyer, 'orders' => $orders];
}

/**
 * DHL's API. `$answers` maps "shipments", "tracking", "pickups",
 * "pickup-cancel", "address" or "pod" to a closure returning the response.
 */
function fakeDhl(array $answers = []): void
{
    Http::fake(function (HttpRequest $request) use ($answers) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $path = Str::after($path, '/mydhlapi/test');

        $key = match (true) {
            $path === '/shipments' => 'shipments',
            $path === '/tracking' => 'tracking',
            $path === '/pickups' && $request->method() === 'POST' => 'pickups',
            str_starts_with($path, '/pickups/') && $request->method() === 'DELETE' => 'pickup-cancel',
            $path === '/address-validate' => 'address',
            $path === '/rates' => 'rates',
            str_ends_with($path, '/proof-of-delivery') => 'pod',
            default => null,
        };

        if ($key !== null && isset($answers[$key])) {
            return $answers[$key]($request);
        }

        if ($key === 'shipments') {
            return Http::response([
                'shipmentTrackingNumber' => DHL_AWB,
                'dispatchConfirmationNumber' => 'CBJ261117000001',
                'packages' => [['referenceNumber' => 1, 'trackingNumber' => 'JD014600004617230770']],
                'documents' => [
                    ['imageFormat' => 'PDF', 'content' => base64_encode('%PDF-1.4 label'), 'typeCode' => 'label'],
                    ['imageFormat' => 'PDF', 'content' => base64_encode('%PDF-1.4 invoice'), 'typeCode' => 'invoice'],
                ],
                'shipmentCharges' => [['currencyType' => 'BILLC', 'priceCurrency' => 'MAD', 'price' => 812.5]],
                'estimatedDeliveryDate' => ['estimatedDeliveryDate' => '2026-11-20T23:59:00Z', 'estimatedDeliveryType' => 'QDDC'],
            ], 201);
        }

        return Http::response(['detail' => 'unexpected '.$path], 500);
    });
}

/** DHL's tracking answer for our waybill with these checkpoints. */
function dhlTracking(array $codes): Closure
{
    $descriptions = [
        'PU' => 'Shipment picked up',
        'AF' => 'Arrived at DHL Sort Facility - CASABLANCA-MA',
        'AR' => 'Arrived at DHL Delivery Facility - PARIS-FR',
        'WC' => 'With delivery courier',
        'OK' => 'Delivered',
        'BA' => 'Bad address',
    ];

    return fn () => Http::response(['shipments' => [[
        'shipmentTrackingNumber' => DHL_AWB,
        'status' => 'Success',
        'events' => array_map(fn (string $code, int $i) => [
            'date' => '2026-11-18',
            'time' => sprintf('%02d:00:00', 8 + $i),
            'GMTOffset' => '+01:00',
            'typeCode' => $code,
            'description' => $descriptions[$code],
            'serviceArea' => [['code' => 'CAS', 'description' => 'Casablanca-MA']],
        ], $codes, array_keys($codes)),
    ]]]);
}

/** The purchase's shipment, as if the desk had already created it. */
function dhlShipment(Order $order, array $overrides = []): DhlShipment
{
    $order->groupOrders()->update(['tracking_number' => DHL_AWB]);

    return DhlShipment::create(array_merge([
        'purchase_reference' => $order->group_reference,
        'tracking_number' => DHL_AWB,
        'product_code' => 'P',
        'incoterm' => 'DAP',
        'planned_shipping_date' => now()->addDay()->toDateString(),
        'packages' => [['weight' => 4, 'length' => 40, 'width' => 40, 'height' => 20]],
        'declared_value' => 800,
        'events' => [],
    ], $overrides));
}

function dhlDraft(Order $order): array
{
    return app(DhlShipmentService::class)->draft($order->groupOrders()->orderBy('id')->get());
}

/** Send the dialog: the draft as offered, with the desk's `$edits`. */
function shipWithDhl($test, User $admin, Order $order, array $edits = [])
{
    return $test->actingAs($admin)
        ->from(route('admin.orders.show', $order->reference))
        ->post(route('admin.orders.dhl.store', $order->reference), array_replace_recursive(dhlDraft($order), $edits));
}

it('offers the shipment as it would be sent, for the desk to check', function () {
    ['admin' => $admin, 'orders' => $orders] = dhlPurchase();

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $orders[0]->reference))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dhl.environment', 'test')
            ->where('dhl.shipment', null)
            ->where('dhl.blocked', null)
            ->where('dhl.draft.receiver.name', 'Claire Martin')
            ->where('dhl.draft.receiver.address_line1', '10 Rue de Rivoli, Paris')
            ->where('dhl.draft.receiver.postal_code', '75001')
            ->where('dhl.draft.receiver.country', 'FR')
            ->where('dhl.draft.receiver.email', 'claire@example.fr')
            ->where('dhl.draft.packages.0.weight', 4)
            ->where('dhl.draft.packages.0.length', 40)
            ->has('dhl.draft.items', 2)
            ->where('dhl.draft.items.0.description', 'Handmade ceramic plate')
            ->where('dhl.draft.items.0.hs_code', '691200')
            ->where('dhl.draft.items.0.weight', 2)
            ->where('cathedis', null)
            ->etc());
});

it('fills the town typed at checkout and leaves it out of the street lines', function () {
    ['orders' => $orders] = dhlPurchase([
        'shipping_address' => 'Claire Martin, 10 Rue de Rivoli, Paris 75001',
        'shipping_city' => 'Paris',
    ], stores: 1);

    $draft = dhlDraft($orders[0]);

    expect($draft['receiver']['city'])->toBe('Paris')
        ->and($draft['receiver']['address_line1'])->toBe('10 Rue de Rivoli')
        ->and($draft['receiver']['address_line2'])->toBe('');
});

it('creates one shipment for the whole purchase, keeps its documents and gives every order its waybill', function () {
    ['admin' => $admin, 'orders' => $orders] = dhlPurchase();
    fakeDhl();

    shipWithDhl($this, $admin, $orders[0], ['receiver' => ['city' => 'Paris']])
        ->assertRedirect()
        ->assertSessionHas('success', 'Expédition DHL créée : '.DHL_AWB.'. Ramassage réservé (CBJ261117000001).');

    $shipment = DhlShipment::sole();
    expect($shipment->tracking_number)->toBe(DHL_AWB)
        ->and($shipment->pickup_confirmation_number)->toBe('CBJ261117000001')
        ->and((float) $shipment->charge)->toBe(812.5)
        ->and($shipment->estimated_delivery_date->toDateString())->toBe('2026-11-20')
        ->and($shipment->documents()->orderBy('id')->pluck('type')->all())->toBe(['label', 'invoice'])
        ->and(Order::pluck('tracking_number')->unique()->all())->toBe([DHL_AWB])
        // Creating it moves nothing: DHL has not collected it yet.
        ->and(Order::pluck('status')->unique()->all())->toBe(['confirmed']);

    Http::assertSent(function (HttpRequest $request) {
        $body = $request->data();
        $receiver = $body['customerDetails']['receiverDetails'];
        $declaration = $body['content']['exportDeclarations'][0];

        return $request->hasHeader('Authorization', 'Basic '.base64_encode('hirfatyMA:secret'))
            && $body['productCode'] === 'P'
            && $body['accounts'] === [['typeCode' => 'shipper', 'number' => '950000000']]
            && $body['pickup']['isRequested'] === true
            && $body['customerDetails']['shipperDetails']['postalAddress']['countryCode'] === 'MA'
            && $receiver['postalAddress'] === ['postalCode' => '75001', 'cityName' => 'Paris', 'countryCode' => 'FR', 'addressLine1' => '10 Rue de Rivoli, Paris']
            && $receiver['contactInformation']['fullName'] === 'Claire Martin'
            && $body['content']['isCustomsDeclarable'] === true
            && $body['content']['declaredValue'] == 800
            && $body['content']['declaredValueCurrency'] === 'MAD'
            && $body['content']['incoterm'] === 'DAP'
            && $declaration['incoterm'] === 'DAP'
            && count($declaration['lineItems']) === 2
            && $declaration['lineItems'][0]['commodityCodes'] === [['typeCode' => 'outbound', 'value' => '691200']]
            && $declaration['lineItems'][0]['manufacturerCountry'] === 'MA'
            && $declaration['invoice']['number'] === Order::first()->group_reference
            && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:00:00 GMT[+-]\d{2}:\d{2}$/', $body['plannedShippingDateAndTime']) === 1;
    });
});

it('never creates a second shipment for the same purchase', function () {
    ['admin' => $admin, 'orders' => $orders] = dhlPurchase(stores: 1);
    dhlShipment($orders[0]);
    fakeDhl();

    shipWithDhl($this, $admin, $orders[0], ['receiver' => ['city' => 'Paris']])
        ->assertSessionHas('error', 'DHL : Une expédition DHL existe déjà pour cet achat ('.DHL_AWB.').');

    Http::assertNothingSent();
});

it('tells the desk why a purchase cannot ship at all, without calling DHL', function (array $setup, string $reason) {
    ['admin' => $admin, 'orders' => $orders] = dhlPurchase($setup['order'] ?? [], stores: 1);

    foreach ($setup['config'] ?? [] as $key => $value) {
        config([$key => $value]);
    }

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $orders[0]->reference))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dhl.blocked', $reason)
            ->where('dhl.draft', null)
            ->etc());
})->with([
    'no login' => [['config' => ['services.dhl_express.password' => null]], 'Identifiants DHL Express non configurés (DHL_EXPRESS_USERNAME / DHL_EXPRESS_PASSWORD / DHL_EXPRESS_ACCOUNT_NUMBER).'],
    'no shipper address' => [['config' => ['services.dhl_express.shipper.address' => null, 'services.dhl_express.shipper.postal_code' => '']], 'Adresse d’expédition DHL incomplète : renseignez DHL_EXPRESS_SHIPPER_ADDRESS, DHL_EXPRESS_SHIPPER_POSTAL_CODE.'],
    'delivered' => [['order' => ['status' => 'delivered']], 'Cet achat est déjà livré.'],
]);

it('hides the panel for a purchase another carrier ships', function () {
    ['admin' => $admin, 'orders' => $orders] = dhlPurchase(stores: 1);
    $other = DeliveryService::create(['slug' => 'cathedis', 'name' => 'Cathedis']);
    $orders[0]->update(['delivery_service_id' => $other->id]);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $orders[0]->reference))
        ->assertInertia(fn (Assert $page) => $page->where('dhl', null)->etc());
});

it('points the desk at what to check before sending', function () {
    ['orders' => $orders] = dhlPurchase(['shipping_country' => null, 'shipping_phone' => '06 12 34 56 78'], stores: 1);
    $orders[0]->items()->first()->product->update(['hs_code' => null, 'weight' => null]);

    $warnings = app(DhlShipmentService::class)->panel(collect([$orders[0]->fresh()]))['warnings'];

    expect(implode("\n", $warnings))
        ->toContain('Le pays de destination n’a pas été enregistré')
        ->toContain('indicatif international')
        ->toContain('Code SH manquant pour : Plat céramique 1')
        ->toContain('Poids inconnu pour : Plat céramique 1 (compté 1 kg)')
        ->toContain('Environnement de TEST DHL');
});

it('shows DHL’s field-by-field refusal and leaves the purchase unshipped', function () {
    ['admin' => $admin, 'orders' => $orders] = dhlPurchase(stores: 1);
    fakeDhl(['shipments' => fn () => Http::response([
        'instance' => '/expressapi/shipments',
        'detail' => 'Multiple problems found, see Additional Details',
        'title' => 'Bad request',
        'additionalDetails' => ['7147: The shipment description Match Keyword. Please update the description and retry.'],
        'status' => '400',
    ], 400)]);

    shipWithDhl($this, $admin, $orders[0], ['receiver' => ['city' => 'Paris']])
        ->assertSessionHas('error', 'DHL : Multiple problems found, see Additional Details — 7147: The shipment description Match Keyword. Please update the description and retry.');

    expect(DhlShipment::count())->toBe(0)
        ->and($orders[0]->fresh()->tracking_number)->toBeNull();
});

it('tells the desk to ship without a pickup when only the courier booking fails', function () {
    ['admin' => $admin, 'orders' => $orders] = dhlPurchase(stores: 1);
    fakeDhl(['shipments' => fn () => Http::response([
        'detail' => 'Multiple problems found, see Additional Details',
        'additionalDetails' => ['202401: No billing address found', '998: Booking Pickup Failed. Please try again another time.'],
    ], 400)]);

    shipWithDhl($this, $admin, $orders[0], ['receiver' => ['city' => 'Paris']])
        ->assertSessionHas('error', fn (string $error) => str_contains($error, '202401: No billing address found')
            && str_contains($error, 'décochez « Réserver le coursier DHL »'));

    expect(DhlShipment::count())->toBe(0);
});

it('has DHL check the shipment without creating it', function () {
    ['admin' => $admin, 'orders' => $orders] = dhlPurchase(stores: 1);
    fakeDhl(['shipments' => fn () => Http::response(['warnings' => []], 200)]);

    $this->actingAs($admin)
        ->postJson(route('admin.orders.dhl.check', $orders[0]->reference), array_replace_recursive(dhlDraft($orders[0]), ['receiver' => ['city' => 'Paris']]))
        ->assertOk()
        ->assertJson(['ok' => true]);

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/shipments?validateDataOnly=true'));
    expect(DhlShipment::count())->toBe(0);
});

it('refuses a field DHL would reject, without calling it', function (array $edits, string $field) {
    ['admin' => $admin, 'orders' => $orders] = dhlPurchase(stores: 1);
    fakeDhl();

    shipWithDhl($this, $admin, $orders[0], array_replace_recursive(['receiver' => ['city' => 'Paris']], $edits))
        ->assertSessionHasErrors($field);

    Http::assertNothingSent();
})->with([
    'no town' => [['receiver' => ['city' => '']], 'receiver.city'],
    'address line too long' => [['receiver' => ['address_line1' => str_repeat('a', 46)]], 'receiver.address_line1'],
    'no country' => [['receiver' => ['country' => '']], 'receiver.country'],
    'unknown incoterm' => [['incoterm' => 'EXW'], 'incoterm'],
]);

it('refuses a shipping date DHL would refuse', function () {
    ['admin' => $admin, 'orders' => $orders] = dhlPurchase(stores: 1);
    fakeDhl();

    shipWithDhl($this, $admin, $orders[0], ['receiver' => ['city' => 'Paris'], 'shipping_date' => now()->addDays(30)->toDateString()])
        ->assertSessionHas('error', 'DHL : La date d’expédition doit être entre aujourd’hui et dans 10 jours.');

    Http::assertNothingSent();
});

it('writes customs descriptions in the plain ASCII DHL accepts', function () {
    expect(DhlShipmentService::ascii('Plat en céramique — fait main', 512))->toBe('Plat en ceramique - fait main')
        ->and(DhlShipmentService::ascii(str_repeat('x', 80), 70))->toHaveLength(70);
});

it('downloads the label as a PDF', function () {
    ['admin' => $admin, 'orders' => $orders] = dhlPurchase(stores: 1);
    $shipment = dhlShipment($orders[0]);
    $shipment->documents()->create(['type' => 'label', 'format' => 'PDF', 'content' => base64_encode('%PDF-1.4 label')]);

    $response = $this->actingAs($admin)->get(route('admin.orders.dhl.document', [$orders[0]->reference, 'label']));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toBe('%PDF-1.4 label');

    $this->actingAs($admin)
        ->get(route('admin.orders.dhl.document', [$orders[0]->reference, 'invoice']))
        ->assertStatus(422);
});

it('books and cancels the courier for a shipment created without one', function () {
    ['admin' => $admin, 'orders' => $orders] = dhlPurchase(stores: 1);
    $shipment = dhlShipment($orders[0]);
    fakeDhl([
        'pickups' => fn () => Http::response(['dispatchConfirmationNumbers' => ['PRG261118000062']], 201),
        'pickup-cancel' => fn () => Http::response(['message' => 'Successfully cancelled.'], 200),
    ]);

    $this->actingAs($admin)
        ->from(route('admin.orders.show', $orders[0]->reference))
        ->post(route('admin.orders.dhl.pickup', $orders[0]->reference), [
            'date' => now()->addDay()->toDateString(),
            'close_time' => '18:00',
            'location' => 'reception',
        ])
        ->assertSessionHas('success', 'Ramassage DHL réservé : PRG261118000062.');

    expect($shipment->fresh()->hasPickup())->toBeTrue();
    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'POST'
        && $request['shipmentDetails'][0]['shipmentTrackingNumber'] === DHL_AWB
        && $request['shipmentDetails'][0]['packages'][0]['weight'] == 4);

    $this->actingAs($admin)
        ->from(route('admin.orders.show', $orders[0]->reference))
        ->delete(route('admin.orders.dhl.pickup.cancel', $orders[0]->reference))
        ->assertSessionHas('success', 'Ramassage DHL annulé. (Successfully cancelled.)');

    expect($shipment->fresh()->hasPickup())->toBeFalse();
    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/pickups/PRG261118000062?requestorName=Desk+Hirfaty'));
});

it('carries DHL checkpoints onto the purchase', function () {
    Notification::fake();
    ['buyer' => $buyer, 'orders' => $orders] = dhlPurchase();
    $shipment = dhlShipment($orders[0]);

    $answers = [
        dhlTracking(['PU']),
        dhlTracking(['PU']),
        dhlTracking(['PU', 'AF', 'AR', 'WC']),
        dhlTracking(['PU', 'AF', 'AR', 'WC', 'OK']),
    ];
    fakeDhl(['tracking' => function () use (&$answers) {
        return array_shift($answers)();
    }]);

    // Picked up: the purchase is shipped, and the buyer told once.
    $this->artisan('dhl:refresh-shipments')->assertSuccessful();
    expect(Order::pluck('status')->unique()->all())->toBe(['shipped'])
        ->and($shipment->fresh()->status_code)->toBe('PU');
    Notification::assertSentToTimes($buyer, OrderShipped::class, 1);

    // The same checkpoints again write nothing new.
    $this->artisan('dhl:refresh-shipments')->assertSuccessful();
    expect($orders[0]->statusEvents()->whereNotNull('note')->count())->toBe(1);

    // In transit: lines on the tracking page, no email.
    $this->artisan('dhl:refresh-shipments')->assertSuccessful();
    expect($orders[0]->statusEvents()->get()->last()->note)->toBe('Colis en cours de livraison par le livreur DHL.');
    Notification::assertSentToTimes($buyer, OrderShipped::class, 1);

    // Delivered: the purchase is delivered and no longer polled.
    $this->artisan('dhl:refresh-shipments')->assertSuccessful();
    expect(Order::pluck('status')->unique()->all())->toBe(['delivered'])
        ->and($shipment->fresh()->closed_at)->not->toBeNull()
        ->and($shipment->fresh()->events)->toHaveCount(5);
    Notification::assertSentToTimes($buyer, OrderDelivered::class, 1);

    $this->artisan('dhl:refresh-shipments')->expectsOutputToContain('0 DHL shipment(s) checked')->assertSuccessful();

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/tracking?shipmentTrackingNumber='.DHL_AWB.'&trackingView=all-checkpoints'));
});

it('alerts the desk when DHL reports a bad address, without cancelling anything', function () {
    Notification::fake();
    ['admin' => $admin, 'orders' => $orders] = dhlPurchase(stores: 1);
    dhlShipment($orders[0]);
    fakeDhl(['tracking' => dhlTracking(['PU', 'BA'])]);

    $this->artisan('dhl:refresh-shipments')->assertSuccessful();

    expect($orders[0]->fresh()->status)->toBe('shipped');
    Notification::assertSentTo($admin, DhlDeliveryIssue::class, fn (DhlDeliveryIssue $issue) => $issue->status === 'BA — Bad address'
        && $issue->trackingNumber === DHL_AWB);
});

it('suggests the towns DHL files a postcode under', function () {
    ['admin' => $admin] = dhlPurchase(stores: 1);
    fakeDhl(['address' => fn () => Http::response(['address' => [
        ['countryCode' => 'FR', 'postalCode' => '75001', 'cityName' => 'PARIS'],
    ]])]);

    $this->actingAs($admin)
        ->getJson(route('admin.dhl.towns', ['country' => 'FR', 'postal_code' => '75001']))
        ->assertOk()
        ->assertJson(['towns' => ['Paris']]);
});

it('checks the DHL login and account without shipping anything', function () {
    fakeDhl([
        'address' => fn () => Http::response(['address' => [['cityName' => 'PARIS']]]),
        'rates' => fn () => Http::response(['products' => [[
            'productName' => 'EXPRESS WORLDWIDE',
            'productCode' => 'P',
            'totalPrice' => [['currencyType' => 'BILLC', 'priceCurrency' => 'MAD', 'price' => 640.2]],
        ]]]),
    ]);

    $this->artisan('dhl:check')
        ->expectsOutputToContain('(TEST)')
        ->expectsOutputToContain('✓ Identifiants acceptés — 75001 (FR) : PARIS')
        ->expectsOutputToContain('✓ Compte accepté — EXPRESS WORLDWIDE (P) : 640.2 MAD')
        ->assertSuccessful();

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/rates?')
        && $request['accountNumber'] === '950000000'
        && $request['originCountryCode'] === 'MA');
});

it('says when DHL refuses the login', function () {
    fakeDhl(['address' => fn () => Http::response(['title' => 'Unauthorized'], 401)]);

    $this->artisan('dhl:check')
        ->expectsOutputToContain('DHL Express refuse les identifiants (HTTP 401)')
        ->assertFailed();
});

it('keeps DHL for the desk', function () {
    ['buyer' => $buyer, 'orders' => $orders] = dhlPurchase(stores: 1);

    $this->actingAs($buyer)->post(route('admin.orders.dhl.store', $orders[0]->reference))->assertRoleDenied();
    $this->actingAs($buyer)->get(route('admin.dhl.towns', ['country' => 'FR', 'postal_code' => '75001']))->assertRoleDenied();
});
