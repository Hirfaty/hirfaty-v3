<?php

/*
 * Rendered previews of every transactional email, for checking copy and layout
 * across the three locales without having to trigger the real flow.
 *
 * Two things gate these, because they render *live* rows rather than fixtures
 * — the order routes below pull the first order in the database and print the
 * buyer's name, address and phone onto the page:
 *
 *  1. This file is only `require`d outside production (see routes/web.php).
 *  2. Every route here is `role:super_admin`, not merely `auth`. They used to
 *     be the latter, which meant any signed-up shopper could read a stranger's
 *     delivery details by guessing a preview URL.
 */

use App\Models\Order;
use App\Services\LoginCodeService;

Route::get('/preview/email/order-placed/{locale}', function (string $locale) {
    // A single checkout can span several stores (CheckoutService splits one
    // cart into one Order per store), so the preview mirrors that: every order
    // sharing the first one's group_reference, the same set OrderPlaced mails.
    $first = Order::with(['store', 'items.product', 'buyer'])->firstOrFail();
    $orders = Order::where('group_reference', $first->group_reference)
        ->with(['store', 'items.product'])
        ->get();
    $user = $first->buyer;
    $user->locale = $locale;

    return view("emails.buyer.order-placed.{$locale}", [
        'orders' => $orders,
        'reference' => $first->group_reference,
        'user' => $user,
    ]);
})->middleware(['auth', 'role:super_admin']);

Route::get('/preview/email/seller/new-order/{locale}', function (string $locale) {
    $order = Order::with(['store.user', 'buyer', 'items.product'])->first();
    $user = $order->store->user;
    $user->locale = $locale;

    return view("emails.seller.order.new.{$locale}", compact('order', 'user'));
})->middleware(['auth', 'role:super_admin']);

Route::get('/preview/email/buyer/cancelled/{locale}', function (string $locale) {
    $first = Order::with(['store', 'buyer', 'items.product'])->firstOrFail();
    $orders = Order::where('group_reference', $first->group_reference)
        ->with(['store', 'items.product'])
        ->get()
        ->each(fn (Order $order) => $order->cancellation_reason = 'Product is no longer available in stock.');
    $reference = $first->group_reference ?? $first->reference;
    $user = $first->buyer;
    $user->locale = $locale;

    return view("emails.buyer.order-status.cancelled.{$locale}", compact('orders', 'reference', 'user'));
})->middleware(['auth', 'role:super_admin']);

/*
 * The rest of the order-status lifecycle: confirmed, shipped, delivered.
 * Same grouped shape as /preview/email/order-placed above — every order
 * sharing the first one's group_reference, since a purchase spanning several
 * workshops is one email, not one per store (see OrderConfirmed's docblock).
 * One route per event since each needs slightly different mock state on the
 * orders (a tracking number for shipped, a delivered_at for delivered).
 */
Route::get('/preview/email/buyer/confirmed/{locale}', function (string $locale) {
    $first = Order::with(['store', 'buyer', 'items.product'])->firstOrFail();
    $orders = Order::where('group_reference', $first->group_reference)
        ->with(['store', 'items.product'])
        ->get();
    $reference = $first->group_reference ?? $first->reference;
    $user = $first->buyer;
    $user->locale = $locale;

    return view("emails.buyer.order-status.confirmed.{$locale}", compact('orders', 'reference', 'user'));
})->middleware(['auth', 'role:super_admin']);

Route::get('/preview/email/buyer/shipped/{locale}', function (string $locale) {
    $first = Order::with(['store', 'buyer', 'items.product'])->firstOrFail();
    $orders = Order::where('group_reference', $first->group_reference)
        ->with(['store', 'items.product'])
        ->get()
        ->each(fn (Order $order) => $order->tracking_number = 'AMN-2K8F4M9Q');
    $reference = $first->group_reference ?? $first->reference;
    $user = $first->buyer;
    $user->locale = $locale;

    return view("emails.buyer.order-status.shipped.{$locale}", compact('orders', 'reference', 'user'));
})->middleware(['auth', 'role:super_admin']);

Route::get('/preview/email/buyer/delivered/{locale}', function (string $locale) {
    $first = Order::with(['store', 'buyer', 'items.product'])->firstOrFail();
    $orders = Order::where('group_reference', $first->group_reference)
        ->with(['store', 'items.product'])
        ->get()
        ->each(fn (Order $order) => $order->delivered_at = now());
    $reference = $first->group_reference ?? $first->reference;
    $user = $first->buyer;
    $user->locale = $locale;

    return view("emails.buyer.order-status.delivered.{$locale}", compact('orders', 'reference', 'user'));
})->middleware(['auth', 'role:super_admin']);

/*
 * Trade-request verdicts, both outcomes and all three locales:
 *   /preview/email/professional/approved/fr ... /professional/declined/ar
 */
Route::get('/preview/email/professional/{outcome}/{locale}', function (string $outcome, string $locale) {
    abort_unless(in_array($outcome, ['approved', 'declined'], true), 404);
    abort_unless(in_array($locale, ['fr', 'en', 'ar'], true), 404);

    return view("emails.buyer.professional.{$outcome}.{$locale}", [
        'user' => auth()->user(),
        'reason' => 'Nous n’avons pas pu confirmer l’existence de l’établissement.',
    ]);
})->middleware(['auth', 'role:super_admin']);

/*
 * Verification email, both audiences and all three locales:
 *   /preview/email/verify/buyer/fr   ...   /preview/email/verify/seller/ar
 */
Route::get('/preview/email/verify/{audience}/{locale}', function (string $audience, string $locale) {
    abort_unless(in_array($audience, ['buyer', 'seller'], true), 404);
    abort_unless(in_array($locale, ['fr', 'en', 'ar'], true), 404);

    return view('emails.verify', [
        'url' => url('/verify-email/1/preview-signature'),
        'user' => auth()->user(),
        'locale' => $locale,
        'audience' => $audience,
        'expiresInMinutes' => config('auth.verification.expire', 60),
    ]);
})->middleware(['auth', 'role:super_admin']);

/*
 * The 6-digit code email, both purposes and all three locales:
 *   /preview/email/code/registration_code/fr ... /code/login_code/ar
 *
 * No `user` is passed: the signup variant deliberately goes out before the
 * account exists, so the shared template has no first name to greet with.
 */
Route::get('/preview/email/code/{copy}/{locale}', function (string $copy, string $locale) {
    abort_unless(in_array($copy, ['registration_code', 'login_code'], true), 404);
    abort_unless(in_array($locale, ['fr', 'en', 'ar'], true), 404);

    return view('emails.auth-code', [
        'code' => '480913',
        'locale' => $locale,
        /*
         * Read off a class that *uses* the trait, not off the trait itself:
         * PHP 8.4 fatals on `GeneratesEmailCodes::CODE_TTL_MINUTES`
         * ("Cannot access trait constant directly"), which 500'd this preview
         * for its whole life. Either consumer would do — RegistrationCodeService
         * and LoginCodeService share the constant precisely so the signup and
         * sign-in codes cannot expire on different schedules.
         */
        'expiresInMinutes' => LoginCodeService::CODE_TTL_MINUTES,
        'copy' => $copy,
    ]);
})->middleware(['auth', 'role:super_admin']);
