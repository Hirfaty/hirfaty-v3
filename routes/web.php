<?php

use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\StoreSetupController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Models\User;
use App\Http\Controllers\Seller\OrderController;
use App\Http\Controllers\Seller\EarningController;
use App\Http\Controllers\Buyer\ProductController as BuyerProductController;
use App\Http\Controllers\Buyer\CheckoutController;
use App\Http\Controllers\Buyer\CartController;



Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
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


//Seller
Route::middleware(['auth', 'verified', 'role:seller'])
    ->prefix('seller')
    ->name('seller.')
    ->group(function () {

        // Dashboard
        Route::get("/dashboard", [DashboardController::class, 'index'])->name("dashboard");

        //Set up Store
        Route::get('/setup-store', [StoreSetupController::class, 'show'])->name('setup.store');
        Route::post('/setup-store', [StoreSetupController::class, 'store']);

        //Products
        Route::resource('products', ProductController::class);

        //Orders
        Route::get('orders',                        [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}',                [OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status',       [OrderController::class, 'updateStatus'])->name('orders.status');
    
        //Earnings
        Route::get('earnings', [EarningController::class, 'index'])->name('earnings.index');
    });

Route::middleware('auth')->group(function () {
    Route::post('/products/{product}/wishlist', [BuyerProductController::class, 'toggleWishlist'])->name('products.wishlist');
});


Route::middleware(['auth', 'verified', 'role:buyer'])->group(function () {
    Route::get('/cart',                     [CartController::class, 'show'])->name('cart.show');
    Route::post('/cart',                    [CartController::class, 'add'])->name('cart.add');
    Route::patch('/cart/{cartItem}',        [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{cartItem}',       [CartController::class, 'remove'])->name('cart.remove');

    Route::get('/checkout',  [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
});

// Public routes — no auth needed
Route::get('/products', [BuyerProductController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [BuyerProductController::class, 'show'])->name('products.show');

require __DIR__.'/settings.php';
