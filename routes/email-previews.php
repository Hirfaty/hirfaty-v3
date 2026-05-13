<?php

Route::get('/preview/email/order-placed/{locale}', function (string $locale) {
    $order = \App\Models\Order::with(['store', 'items.product', 'buyer'])->first();
    $user  = $order->buyer;
    $user->locale = $locale;
    return view("emails.buyer.order-placed.{$locale}", [
        'order' => $order,
        'user'  => $user,
    ]);
})->middleware('auth');

Route::get('/preview/email/seller/new-order/{locale}', function (string $locale) {
    $order = \App\Models\Order::with(['store.user', 'buyer', 'items.product'])->first();
    $user  = $order->store->user;
    $user->locale = $locale;
    return view("emails.seller.order.new.{$locale}", compact('order', 'user'));
})->middleware('auth');

Route::get('/preview/email/buyer/cancelled/{locale}', function (string $locale) {
    $order = \App\Models\Order::with(['store', 'buyer', 'items.product'])->first();
    $order->cancellation_reason = 'Product is no longer available in stock.';
    $user  = $order->buyer;
    $user->locale = $locale;
    return view("emails.buyer.order-status.cancelled.{$locale}", compact('order', 'user'));
})->middleware('auth');