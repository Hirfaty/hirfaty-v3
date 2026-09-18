<?php

use App\Concerns\GeneratesEmailCodes;
use App\Models\Order;

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
})->middleware('auth');

Route::get('/preview/email/seller/new-order/{locale}', function (string $locale) {
    $order = Order::with(['store.user', 'buyer', 'items.product'])->first();
    $user = $order->store->user;
    $user->locale = $locale;

    return view("emails.seller.order.new.{$locale}", compact('order', 'user'));
})->middleware('auth');

Route::get('/preview/email/buyer/cancelled/{locale}', function (string $locale) {
    $order = Order::with(['store', 'buyer', 'items.product'])->first();
    $order->cancellation_reason = 'Product is no longer available in stock.';
    $user = $order->buyer;
    $user->locale = $locale;

    return view("emails.buyer.order-status.cancelled.{$locale}", compact('order', 'user'));
})->middleware('auth');

/*
 * The rest of the order-status lifecycle: confirmed, shipped, delivered.
 * Same shape as /preview/email/buyer/cancelled above, one route per event
 * since each needs slightly different mock state on the order (a tracking
 * number for shipped, a delivered_at for delivered).
 */
Route::get('/preview/email/buyer/confirmed/{locale}', function (string $locale) {
    $order = Order::with(['store', 'buyer', 'items.product'])->first();
    $user = $order->buyer;
    $user->locale = $locale;

    return view("emails.buyer.order-status.confirmed.{$locale}", compact('order', 'user'));
})->middleware('auth');

Route::get('/preview/email/buyer/shipped/{locale}', function (string $locale) {
    $order = Order::with(['store', 'buyer', 'items.product'])->first();
    $order->tracking_number = 'AMN-2K8F4M9Q';
    $user = $order->buyer;
    $user->locale = $locale;

    return view("emails.buyer.order-status.shipped.{$locale}", compact('order', 'user'));
})->middleware('auth');

Route::get('/preview/email/buyer/delivered/{locale}', function (string $locale) {
    $order = Order::with(['store', 'buyer', 'items.product'])->first();
    $order->delivered_at = now();
    $user = $order->buyer;
    $user->locale = $locale;

    return view("emails.buyer.order-status.delivered.{$locale}", compact('order', 'user'));
})->middleware('auth');

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
})->middleware('auth');

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
})->middleware('auth');

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
        'expiresInMinutes' => GeneratesEmailCodes::CODE_TTL_MINUTES,
        'copy' => $copy,
    ]);
})->middleware('auth');
