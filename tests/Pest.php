<?php

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
 * A signed-in user on a route their role does not allow. RoleMiddleware
 * sends them to the catalogue with an error flash rather than a bare 403,
 * so they land somewhere useful and see why.
 */
TestResponse::macro('assertRoleDenied', function () {
    /** @var TestResponse $this */
    return $this->assertRedirect(route('products.index'))
        ->assertSessionHas('error');
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Pay for a card purchase the way Payzone confirms one: a CHARGED
 * notification for an attempt against it, handled by PaymentService.
 *
 * Every checkout is paid by card now, and an order is only placed — visible,
 * earning, announced, cart cleared — once that confirmation arrives. Tests
 * that check out and then look at the placed purchase call this in between.
 * Defaults to the most recent purchase.
 */
function confirmCardPayment(?string $reference = null): void
{
    $reference ??= Order::withoutGlobalScope(Order::SETTLED_SCOPE)->latest('id')->value('group_reference');

    // Nothing to pay for: the checkout under test was refused.
    if ($reference === null) {
        return;
    }

    $orders = Order::withoutGlobalScope(Order::SETTLED_SCOPE)->where('group_reference', $reference)->get();
    $payments = app(PaymentService::class);

    $payment = Payment::create([
        'group_reference' => $reference,
        'buyer_id' => $orders->first()?->buyer_id,
        'charge_id' => Payment::newChargeId(),
        'amount' => $payments->amountFor($orders),
        'currency' => 'MAD',
    ]);

    $payments->handleNotification([
        'id' => $payment->charge_id,
        'status' => 'CHARGED',
        'lineItem' => ['amount' => (float) $payment->amount, 'currency' => 'MAD'],
        'transactions' => [['type' => 'CHARGE', 'state' => 'APPROVED', 'resultCode' => 0, 'amount' => (float) $payment->amount]],
        'paymentMethod' => 'Visa',
    ]);
}
