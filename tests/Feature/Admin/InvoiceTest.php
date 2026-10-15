<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
    $this->buyer = User::factory()->create([
        'role' => 'buyer',
        'email_verified_at' => now(),
        'first_name' => 'Yasmine',
        'last_name' => 'Alaoui',
        'email' => 'yasmine@example.com',
    ]);
});

function invoiceStore(string $name): Store
{
    return Store::create([
        'user_id' => User::factory()->create(['role' => 'seller'])->id,
        'store_name' => $name,
        'slug' => Str::slug($name.'-'.Str::random(6)),
        'status' => 'active',
    ]);
}

/**
 * One workshop's share of a checkout, with one 1-piece line. `$group` ties it
 * to the others; null makes it a lone order from before grouping existed.
 */
function invoiceOrder(
    User $buyer,
    Store $store,
    float $price,
    ?string $group,
    string $method = 'cod',
    string $paymentStatus = 'pending',
    string $status = 'pending',
    float $deliveryFee = 0,
): Order {
    $product = Product::create([
        'store_id' => $store->id,
        'name' => 'Pièce '.Str::random(4),
        'slug' => 'piece-'.Str::random(8),
        'base_price' => $price,
        'stock' => 5,
        'status' => 'published',
    ]);

    $order = Order::create([
        'buyer_id' => $buyer->id,
        'store_id' => $store->id,
        'group_reference' => $group,
        'status' => $status,
        'payment_method' => $method,
        'payment_status' => $paymentStatus,
        'total_amount' => $price,
        'platform_fee' => round($price * 0.2, 2),
        'delivery_fee' => $deliveryFee,
        'seller_earnings' => $price * 0.8,
        'shipping_address' => '12 Rue Ibn Battouta',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => $price,
        'subtotal' => $price,
    ]);

    return $order;
}

function invoicePayPalPayment(User $buyer, string $group, float $amount, string $status = 'paid'): Payment
{
    return Payment::create([
        'group_reference' => $group,
        'buyer_id' => $buyer->id,
        'provider' => 'paypal',
        'charge_id' => Payment::newChargeId(),
        'amount' => $amount,
        'currency' => 'EUR',
        'exchange_rate' => 0.092,
        'status' => $status,
        'provider_transaction_id' => '3C679366HH908993F',
        'paid_at' => now(),
    ]);
}

test('the invoices page lists one invoice per purchase, with what the buyer paid', function () {
    $alpha = invoiceStore('Atelier Alpha');
    $beta = invoiceStore('Atelier Beta');

    // A basket split across two workshops, cash on delivery: one invoice.
    invoiceOrder($this->buyer, $alpha, 100, 'HRFG-SPLIT00001', deliveryFee: 30);
    invoiceOrder($this->buyer, $beta, 60, 'HRFG-SPLIT00001');
    // From before grouping: numbered after the order itself, and delivered.
    $legacy = invoiceOrder($this->buyer, $alpha, 80, null, status: 'delivered');
    // Paid with PayPal, in euros.
    invoiceOrder($this->buyer, $beta, 200, 'HRFG-PAYPAL0001', 'paypal', 'paid', deliveryFee: 40);
    invoicePayPalPayment($this->buyer, 'HRFG-PAYPAL0001', 22.08);
    // Sent to PayPal and never paid: no purchase, so no invoice.
    invoiceOrder($this->buyer, $alpha, 90, 'HRFG-UNPAID0001', 'paypal', 'pending');

    $this->actingAs($this->admin)
        ->get(route('admin.invoices.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/invoices/index')
            ->has('invoices', 3)
            ->where('pagination.total', 3)
            ->where('counts', ['all' => 3, 'card' => 0, 'paypal' => 1, 'cod' => 2])
            // Newest first.
            ->has('invoices.0', fn (Assert $invoice) => $invoice
                ->where('number', 'HRFG-PAYPAL0001')
                ->where('buyer.name', 'Yasmine Alaoui')
                ->where('total', 240)
                ->where('payment_method', 'paypal')
                ->where('state', 'paid')
                ->where('payment', [
                    'provider' => 'paypal',
                    'amount' => 22.08,
                    'currency' => 'EUR',
                    'transaction_id' => '3C679366HH908993F',
                ])
                // What the buyer paid, never Hirfaty's commission.
                ->missing('platform_fee')
                ->etc())
            ->where('invoices.1.number', $legacy->reference)
            ->where('invoices.1.state', 'collected')
            ->has('invoices.2', fn (Assert $invoice) => $invoice
                ->where('number', 'HRFG-SPLIT00001')
                ->where('products_total', 160)
                ->where('delivery_total', 30)
                ->where('total', 190)
                ->where('stores', ['Atelier Alpha', 'Atelier Beta'])
                ->where('state', 'to_collect')
                ->where('payment', null)
                ->etc()));
});

test('the payment tabs and the search narrow the invoices', function () {
    $alpha = invoiceStore('Atelier Alpha');
    $other = User::factory()->create(['role' => 'buyer', 'first_name' => 'Karim', 'email' => 'karim@example.com']);

    invoiceOrder($this->buyer, $alpha, 100, 'HRFG-CASH000001');
    invoiceOrder($other, $alpha, 200, 'HRFG-PAYPAL0001', 'paypal', 'paid');
    invoicePayPalPayment($other, 'HRFG-PAYPAL0001', 18.40);

    $this->actingAs($this->admin)
        ->get(route('admin.invoices.index', ['method' => 'paypal']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices', 1)
            ->where('invoices.0.number', 'HRFG-PAYPAL0001'));

    // By buyer, and by a lowercase piece of the number.
    $this->get(route('admin.invoices.index', ['search' => 'yasmine@']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices', 1)
            ->where('invoices.0.number', 'HRFG-CASH000001')
            // The tabs count under the search.
            ->where('counts.all', 1)
            ->where('counts.paypal', 0));

    $this->get(route('admin.invoices.index', ['search' => 'paypal0001']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices', 1)
            ->where('invoices.0.number', 'HRFG-PAYPAL0001'));
});

test('refunds and cancellations show on the invoice', function () {
    $alpha = invoiceStore('Atelier Alpha');
    $beta = invoiceStore('Atelier Beta');

    // One workshop of a PayPal purchase cancelled and refunded.
    invoiceOrder($this->buyer, $alpha, 100, 'HRFG-PARTIAL001', 'paypal', 'paid', deliveryFee: 30);
    invoiceOrder($this->buyer, $beta, 60, 'HRFG-PARTIAL001', 'paypal', 'refunded', 'cancelled');
    invoicePayPalPayment($this->buyer, 'HRFG-PARTIAL001', 17.48);
    // Cash, called off before delivery.
    invoiceOrder($this->buyer, $alpha, 50, 'HRFG-CANCEL0001', status: 'cancelled');

    $this->actingAs($this->admin)
        ->get(route('admin.invoices.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoices.0.number', 'HRFG-CANCEL0001')
            ->where('invoices.0.state', 'cancelled')
            ->where('invoices.1.number', 'HRFG-PARTIAL001')
            ->where('invoices.1.state', 'partially_refunded')
            // Still invoiced in full: the refund is shown, not netted off.
            ->where('invoices.1.total', 190));
});

test('the desk downloads any buyer\'s invoice as a PDF', function () {
    $alpha = invoiceStore('Atelier Alpha');
    invoiceOrder($this->buyer, $alpha, 100, 'HRFG-INVC000001', deliveryFee: 30);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.invoices.download', 'HRFG-INVC000001'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('HRFG-INVC000001')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');

    $this->get(route('admin.invoices.download', 'HRFG-NOPE000001'))->assertNotFound();
});

test('only the desk sees invoices', function () {
    $alpha = invoiceStore('Atelier Alpha');
    invoiceOrder($this->buyer, $alpha, 100, 'HRFG-INVC000001');

    $this->actingAs($this->buyer)->get(route('admin.invoices.index'))->assertRoleDenied();
    $this->actingAs($this->buyer)->get(route('admin.invoices.download', 'HRFG-INVC000001'))->assertRoleDenied();
});
