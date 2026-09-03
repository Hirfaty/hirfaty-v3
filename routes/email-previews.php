<?php

use App\Models\Order;

Route::get('/preview/email/order-placed/{locale}', function (string $locale) {
    $order = Order::with(['store', 'items.product', 'buyer'])->first();
    $user = $order->buyer;
    $user->locale = $locale;

    return view("emails.buyer.order-placed.{$locale}", [
        'order' => $order,
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
