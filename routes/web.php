<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Buyer\CartController;
use App\Http\Controllers\Buyer\CheckoutController;
use App\Http\Controllers\Buyer\OrderController as BuyerOrderController;
use App\Http\Controllers\Buyer\ProductController as BuyerProductController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\EarningController;
use App\Http\Controllers\Seller\OrderController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\StoreSetupController;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Static marketing page — no backend data, so no dedicated controller.
Route::inertia('/about', 'about')->name('about');

Route::middleware(['auth', 'verified', 'store.setup'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill(); // marks email as verified

    $user = $request->user();

    if ($user->role === 'seller') {
        return redirect('/seller/setup-store');
    }

    return redirect('/dashboard');
})->middleware(['auth', 'signed'])->name('verification.verify');

// Super Admin
Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');
        // Route::redirect("/dashboard", '/admin/products')->name('dashboard');

        // Sellers
        // Route::get('/sellers',              [Admin\SellerController::class, 'index'])->name('sellers.index');
        // Route::get('/sellers/{user}',       [Admin\SellerController::class, 'show'])->name('sellers.show');
        // Route::patch('/sellers/{user}/ban', [Admin\SellerController::class, 'ban'])->name('sellers.ban');
        // Route::delete('/sellers/{user}',    [Admin\SellerController::class, 'destroy'])->name('sellers.destroy');

        // // Buyers
        // Route::get('/buyers',              [Admin\BuyerController::class, 'index'])->name('buyers.index');
        // Route::get('/buyers/{user}',       [Admin\BuyerController::class, 'show'])->name('buyers.show');
        // Route::patch('/buyers/{user}/ban', [Admin\BuyerController::class, 'ban'])->name('buyers.ban');
        // Route::delete('/buyers/{user}',    [Admin\BuyerController::class, 'destroy'])->name('buyers.destroy');

        Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [Admin\UserController::class, 'create'])->name('users.create');
        Route::get('/users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}/ban', [Admin\UserController::class, 'ban'])->name('users.ban');
        Route::patch('/users/{user}/verify-professional', [Admin\UserController::class, 'verifyProfessional'])->name('users.verify-professional');
        Route::post('/users/{user}/reset-link', [Admin\UserController::class, 'resetLink'])->name('users.reset-link');
        Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');

        Route::get('/products', [Admin\ProductController::class, 'index'])->name('products.index');
        // `create` and `bulk-destroy` must stay above `{product}`, otherwise
        // they're matched as an id (and Postgres then errors casting the
        // string to the bigint id column, rather than a clean 404).
        Route::get('/products/create', [Admin\ProductController::class, 'create'])->name('products.create');
        Route::delete('/products/bulk-destroy', [Admin\ProductController::class, 'bulkDestroy'])->name('products.bulk-destroy');
        Route::post('/products', [Admin\ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [Admin\ProductController::class, 'edit'])->name('products.edit');
        Route::post('/products/{product}', [Admin\ProductController::class, 'update'])->name('products.update');
        Route::get('/products/{product}', [Admin\ProductController::class, 'show'])->name('products.show');
        Route::patch('/products/{product}/approve', [Admin\ProductController::class, 'approve'])->name('products.approve');
        Route::patch('/products/{product}/reject', [Admin\ProductController::class, 'reject'])->name('products.reject');
        Route::patch('/products/{product}/archive', [Admin\ProductController::class, 'archive'])->name('products.archive');
        Route::delete('/products/{product}', [Admin\ProductController::class, 'destroy'])->name('products.destroy');

        // Orders
        Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
        Route::patch('/orders/{order}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('/orders/export', [Admin\OrderController::class, 'export'])->name('orders.export');

        // Reviews
        Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::patch('/reviews/{review}/flag', [Admin\ReviewController::class, 'flag'])->name('reviews.flag');
        Route::delete('/reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');

        // Coupons
        Route::get('/coupons', [Admin\CouponController::class, 'index'])->name('coupons.index');
        Route::post('/coupons', [Admin\CouponController::class, 'store'])->name('coupons.store');
        Route::delete('/coupons/{coupon}', [Admin\CouponController::class, 'destroy'])->name('coupons.destroy');

        // Payouts
        Route::get('/payouts', [Admin\PayoutController::class, 'index'])->name('payouts.index');
        Route::patch('/payouts/{payout}/process', [Admin\PayoutController::class, 'process'])->name('payouts.process');

        // Categories
        Route::get('/categories', [Admin\CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [Admin\CategoryController::class, 'store'])->name('categories.store');
        Route::patch('/categories/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

        // Cities
        Route::get('/cities', [Admin\CityController::class, 'index'])->name('cities.index');
        Route::post('/cities', [Admin\CityController::class, 'store'])->name('cities.store');
        Route::patch('/cities/{city}', [Admin\CityController::class, 'update'])->name('cities.update');
        Route::delete('/cities/{city}', [Admin\CityController::class, 'destroy'])->name('cities.destroy');
    });

// Seller
Route::middleware(['auth', 'verified', 'role:seller'])
    ->prefix('seller')
    ->name('seller.')
    ->group(function () {
        // Set up Store (Must be accessible even if store is not setup)
        Route::get('/setup-store', [StoreSetupController::class, 'show'])->name('setup.store');
        Route::post('/setup-store', [StoreSetupController::class, 'store']);
    });

Route::middleware(['auth', 'verified', 'role:seller', 'store.setup'])
    ->prefix('seller')
    ->name('seller.')
    ->group(function () {

        // Dashboard
        // Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::redirect('/dashboard', '/seller/orders')->name('dashboard');

        // Products
        Route::resource('products', ProductController::class);

        // Orders
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');

        // Earnings
        Route::get('earnings', [EarningController::class, 'index'])->name('earnings.index');
    });

Route::middleware('auth')->group(function () {
    Route::post('/products/{product}/wishlist', [BuyerProductController::class, 'toggleWishlist'])->name('products.wishlist');
});

// Buyer — both normal and professional buyers shop through these routes;
// only the prices they are shown differ.
Route::middleware(['auth', 'verified', 'role:buyer,professional_buyer'])->group(function () {
    Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
    Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/cart/{cartItem}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{cartItem}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('/cart/merge', [CartController::class, 'merge'])->name('cart.merge');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/orders', [BuyerOrderController::class, 'index'])->name('buyer.account.orders.index');
    Route::get('/orders/{order}', [BuyerOrderController::class, 'show'])->name('buyer.orders.show');
});

// Public routes — no auth needed
Route::get('/products', [BuyerProductController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [BuyerProductController::class, 'show'])->name('products.show');

Route::post('/locale/{locale}', function (string $locale) {
    if (! in_array($locale, ['ar', 'fr', 'en'])) {
        abort(400);
    }
    session(['locale' => $locale]);

    return back();
})->name('locale.switch');

require __DIR__.'/settings.php';
require __DIR__.'/email-previews.php';
