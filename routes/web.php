<?php

use App\Http\Controllers\AccountKindController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginCodeController;
use App\Http\Controllers\Auth\LoginIdentifyController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\SellerRegistrationController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BrochureController;
use App\Http\Controllers\Buyer\AddressController;
use App\Http\Controllers\Buyer\CartController;
use App\Http\Controllers\Buyer\CheckoutController;
use App\Http\Controllers\Buyer\DashboardController as BuyerDashboardController;
use App\Http\Controllers\Buyer\OnboardingController;
use App\Http\Controllers\Buyer\OrderController as BuyerOrderController;
use App\Http\Controllers\Buyer\PaymentMethodController;
use App\Http\Controllers\Buyer\ProductController as BuyerProductController;
use App\Http\Controllers\Buyer\ProfessionalRequestController;
use App\Http\Controllers\Buyer\ProfileController as BuyerProfileController;
use App\Http\Controllers\Buyer\ReviewController;
use App\Http\Controllers\Buyer\SearchController;
use App\Http\Controllers\Buyer\StoreController as BuyerStoreController;
use App\Http\Controllers\Buyer\WishlistController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\EarningController;
use App\Http\Controllers\Seller\OrderController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\SettingsController as SellerSettingsController;
use App\Http\Controllers\Seller\WelcomeController as SellerWelcomeController;
use App\Models\User;
use App\Services\SocialAuthService;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Public journal. Open to guests, like the product listing.
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

// Static marketing page — no backend data, so no dedicated controller.
Route::inertia('/about', 'about')->name('about');

/*
 * The two pages the footer links to. Static: all their copy comes from
 * `lang/<locale>/pages.php` and the contact details from `welcome.contact.*`,
 * so there is nothing for a controller to fetch and `Route::inertia` is the
 * whole implementation.
 */
Route::inertia('/how-it-works', 'how-it-works')->name('how-it-works');
Route::inertia('/contact', 'contact')->name('contact');

/*
 * No app-level `verification.verify` route here on purpose — Fortify already
 * registers one at the same URI (`/email/verify/{id}/{hash}`) bound to
 * VerifyEmailController, which delegates the redirect to the
 * Laravel\Fortify\Contracts\VerifyEmailResponse binding (see
 * FortifyServiceProvider → App\Http\Responses\VerifyEmailResponse).
 *
 * A closure route used to be defined here too, under the exact same name.
 * Because it was registered later, it silently replaced Fortify's route in
 * the named-route lookup: every request actually ran this closure's own
 * hardcoded redirect (seller → /seller/setup-store, everyone else →
 * /dashboard) instead of ever reaching VerifyEmailResponse — so fixing that
 * class's role-based redirect (see its docblock) had no effect until this
 * shadow route was removed.
 */

// Super Admin
Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [Admin\UserController::class, 'create'])->name('users.create');
        // Above `{user}`, or "create"/"edit" would be swallowed as an id.
        Route::get('/users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
        Route::get('/users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
        // POST, not PUT: the form ships a logo/banner as multipart, which
        // browsers can only send on a POST.
        Route::post('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/ban', [Admin\UserController::class, 'ban'])->name('users.ban');
        Route::patch('/users/{user}/verify-professional', [Admin\UserController::class, 'verifyProfessional'])->name('users.verify-professional');
        // Buyer-initiated trade requests: approving promotes the account to
        // professional_buyer, which nothing else outside this pair can do.
        Route::patch('/users/{user}/professional/approve', [Admin\UserController::class, 'approveProfessional'])->name('users.professional.approve');
        Route::patch('/users/{user}/professional/decline', [Admin\UserController::class, 'declineProfessional'])->name('users.professional.decline');
        Route::post('/users/{user}/reset-link', [Admin\UserController::class, 'resetLink'])->name('users.reset-link');
        Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
        Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

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

        // Journal (blog)
        Route::get('/blogs', [Admin\BlogController::class, 'index'])->name('blogs.index');
        // `create` and `images` must stay above `{blog}`, or they're matched
        // as an id — same reasoning as the products routes above.
        Route::get('/blogs/create', [Admin\BlogController::class, 'create'])->name('blogs.create');
        Route::post('/blogs/images', [Admin\BlogController::class, 'uploadImage'])->name('blogs.images.store');
        Route::post('/blogs', [Admin\BlogController::class, 'store'])->name('blogs.store');
        Route::get('/blogs/{blog}/edit', [Admin\BlogController::class, 'edit'])->name('blogs.edit');
        Route::get('/blogs/{blog}/preview', [Admin\BlogController::class, 'preview'])->name('blogs.preview');
        // POST, not PATCH: the form ships a cover image as multipart, which
        // browsers can only send on a POST — see BlogForm's own comment.
        Route::post('/blogs/{blog}', [Admin\BlogController::class, 'update'])->name('blogs.update');
        Route::patch('/blogs/{blog}/archive', [Admin\BlogController::class, 'archive'])->name('blogs.archive');
        Route::patch('/blogs/{blog}/publish', [Admin\BlogController::class, 'publishNow'])->name('blogs.publish');
        Route::delete('/blogs/{blog}', [Admin\BlogController::class, 'destroy'])->name('blogs.destroy');

        // Stores — the review queue a shop sits in before it may sell.
        Route::get('/stores', [Admin\StoreController::class, 'index'])->name('stores.index');
        Route::patch('/stores/{store}/approve', [Admin\StoreController::class, 'approve'])->name('stores.approve');
        Route::patch('/stores/{store}/reject', [Admin\StoreController::class, 'reject'])->name('stores.reject');

        // Brochure leads — read-only, see Admin\BrochureLeadController.
        Route::get('/brochure-leads', [Admin\BrochureLeadController::class, 'index'])->name('brochure-leads.index');

        // Newsletter subscribers — read-only, see Admin\NewsletterSubscriberController.
        Route::get('/newsletter-subscribers', [Admin\NewsletterSubscriberController::class, 'index'])->name('newsletter-subscribers.index');

        // Cities
        Route::get('/cities', [Admin\CityController::class, 'index'])->name('cities.index');
        Route::post('/cities', [Admin\CityController::class, 'store'])->name('cities.store');
        Route::patch('/cities/{city}', [Admin\CityController::class, 'update'])->name('cities.update');
        Route::delete('/cities/{city}', [Admin\CityController::class, 'destroy'])->name('cities.destroy');

        /*
         * Amana's rate card. Index only — these are Amana's figures, loaded by
         * AmanaDeliveryRateSeeder, so there is deliberately no route here that
         * could edit or delete one. See Admin\DeliveryRateController.
         */
        Route::get('/delivery-rates', [Admin\DeliveryRateController::class, 'index'])->name('delivery-rates.index');
    });

/*
 * Seller.
 *
 * One group, not the two this used to be. The split existed so `/setup-store`
 * could be reached by a seller who had no shop yet while `store.setup` bounced
 * them out of everything else — but a seller with no shop is no longer a state
 * that occurs: signup creates the account and the workshop in one transaction
 * (RegistrationCodeService::completeSeller), and admin promotion has always
 * created one too (Admin\UserController). With no such seller to catch, both
 * the wizard and the middleware guarding it were dead weight.
 */
Route::middleware(['auth', 'verified', 'role:seller'])
    ->prefix('seller')
    ->name('seller.')
    ->group(function () {

        // Where seller signup lands: the shop is filed for review and nothing
        // publishes until an admin clears it. Forwards itself to the
        // dashboard — see Seller\WelcomeController.
        Route::get('/welcome', [SellerWelcomeController::class, 'index'])->name('welcome');

        // Dashboard. Was a redirect to /seller/orders while the real overview
        // sat unrouted; the page exists and is the natural landing, so it is
        // wired up again.
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        /*
         * Products, read-only. Sellers no longer file their own listings —
         * Hirfaty's team creates every product for them through
         * Admin\ProductController after speaking to the artisan, so all that
         * is left here is the shop's own view of its catalogue.
         *
         * The write routes (and the `store.sellable` gate on them) are gone
         * rather than merely unlinked, so there is no seller-facing way into
         * the catalogue left for a crafted request to find.
         */
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        /*
         * `whereNumber` matters now that the write routes are gone: a stale
         * bookmark to /seller/products/create would otherwise be matched as an
         * id, and on Postgres casting "create" to the bigint key errors out
         * instead of 404ing cleanly.
         */
        Route::get('products/{product}', [ProductController::class, 'show'])
            ->whereNumber('product')
            ->name('products.show');

        // Orders
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');

        // Earnings
        Route::get('earnings', [EarningController::class, 'index'])->name('earnings.index');

        /*
         * Settings. The controller and the page have both existed for a while
         * but were never routed — SellerSidebar already linked to
         * /seller/settings, which 404'd. It is also where a seller finishes a
         * shop they skipped through at setup (craft, city, description), so
         * the "complete it later" promise depends on these existing.
         */
        Route::get('settings', [SellerSettingsController::class, 'index'])->name('settings.index');
        Route::patch('settings/profile', [SellerSettingsController::class, 'updateProfile'])->name('settings.profile.update');
        Route::patch('settings/store', [SellerSettingsController::class, 'updateStore'])->name('settings.store.update');
        Route::patch('settings/locale', [SellerSettingsController::class, 'updateLocale'])->name('settings.locale.update');
        Route::patch('settings/password', [SellerSettingsController::class, 'updatePassword'])->name('settings.password.update');
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
    // Discount codes are not implemented — see CheckoutController::applyCoupon
    // for why the field exists anyway. Throttled because it is a guessing game
    // until it is real, and will still be one afterwards.
    Route::post('/checkout/coupon', [CheckoutController::class, 'applyCoupon'])
        ->middleware('throttle:10,1')
        ->name('checkout.coupon');

    /*
     * The account area: four sibling pages sharing one left sidebar, defined
     * in resources/js/layouts/buyer/account-layout.tsx. Each is a real route
     * rather than a tab, so every section is linkable and the back button
     * moves between them.
     */
    Route::get('/account', BuyerDashboardController::class)->name('buyer.dashboard');
    Route::get('/orders', [BuyerOrderController::class, 'index'])->name('buyer.account.orders.index');
    Route::get('/wishlists', WishlistController::class)->name('buyer.wishlist');
    Route::get('/payment_method', PaymentMethodController::class)->name('buyer.payment_methods');

    // Was the wishlist's URL before it was pluralised; kept so existing links
    // and bookmarks don't 404.
    Route::get('/wishlist', fn () => redirect()->route('buyer.wishlist'));

    // The identity form on /account. Name only — the password half posts to
    // `user-password.update`, which already exists and returns back().
    Route::patch('/account/profile', [BuyerProfileController::class, 'update'])->name('buyer.profile.update');

    Route::post('/account/addresses', [AddressController::class, 'store'])->name('buyer.addresses.store');
    Route::patch('/account/addresses/{address}', [AddressController::class, 'update'])->name('buyer.addresses.update');
    Route::patch('/account/addresses/{address}/default', [AddressController::class, 'makeDefault'])->name('buyer.addresses.default');
    Route::delete('/account/addresses/{address}', [AddressController::class, 'destroy'])->name('buyer.addresses.destroy');

    Route::get('/orders/{order}', [BuyerOrderController::class, 'show'])->name('buyer.orders.show');

    /*
     * Ratings for one delivered order — every piece in it, plus the shop, in a
     * single submission. Behind `role:buyer` with the rest of this group, but
     * that only establishes *a* buyer; ReviewService re-checks that it is
     * this order's buyer and that the order actually arrived.
     */
    Route::post('/orders/{order}/reviews', [ReviewController::class, 'store'])->name('buyer.orders.reviews.store');

    /*
     * First-run tour, shown once after email verification (VerifyEmailResponse
     * routes new buyers here). Skippable at every step — `skip` and `store`
     * both end with onboarded_at stamped, so it never reappears.
     */
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
    Route::post('/onboarding/skip', [OnboardingController::class, 'skip'])->name('onboarding.skip');

    /*
     * Submitting the request stays gated — there is no account to upgrade
     * without one. Only the GET side is public; see below.
     */
    Route::post('/professional-account', [ProfessionalRequestController::class, 'store'])
        ->middleware('throttle:5,60')
        ->name('professional.request');
});

// Public routes — no auth needed
Route::get('/products', [BuyerProductController::class, 'index'])->name('products.index');

/*
 * Live results behind the header's search field. Public, like the listing it
 * previews, and throttled because it fires as the shopper types.
 */
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])
    ->middleware('throttle:60,1')
    ->name('search.suggestions');

/*
 * Deliberately its own path rather than /products/new: that would be captured
 * by the {slug} route below and looked up as a product named "new", so the
 * page would 404 for as long as no such slug existed and silently open the
 * wrong product the moment one did.
 */
Route::get('/new-arrivals', [BuyerProductController::class, 'newArrivals'])->name('products.new');

Route::get('/products/{slug}', [BuyerProductController::class, 'show'])->name('products.show');

/*
 * A shop's public page. Deliberately not under /seller/*, which is the
 * artisan's own back office behind `role:seller` — pointing shoppers there is
 * the 403 this route exists to replace.
 */
Route::get('/stores/{slug}', [BuyerStoreController::class, 'show'])->name('stores.show');

/*
 * What a trade account is and how to get one.
 *
 * Public, unlike its POST counterpart above: this is the page that has to
 * *sell* the feature, and it was previously behind `role:buyer` — so the one
 * audience it exists to convince, people who have not signed up yet, were the
 * only ones who could never read it. A guest following a "buying wholesale?"
 * prompt landed on the registration form with nothing having explained what
 * they were signing up for.
 *
 * The controller therefore has to tolerate a null user; see its show().
 */
Route::get('/professional-account', [ProfessionalRequestController::class, 'show'])->name('professional.show');

/*
 * The footer's mailing-list signup, and so reachable from every page.
 *
 * Public, because the whole point of putting it in the footer is that a visitor
 * who has never registered can use it — and therefore throttled: it is an
 * unauthenticated endpoint whose job is to write a row, which is precisely the
 * shape of thing that fills a table up overnight if left open.
 */
Route::post('/newsletter', [NewsletterController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('newsletter.store');

/*
 * The homepage brochure.
 *
 * Public and therefore throttled, on the same reasoning as the newsletter
 * above: `request` is an unauthenticated endpoint whose job is to write a row.
 * `download` is throttled a little more loosely — it is gated on the session
 * flag `request` sets, but it streams 35 MB each time it answers, so it is the
 * one worth having a ceiling on regardless.
 */
Route::post('/brochure', [BrochureController::class, 'request'])
    ->middleware('throttle:5,1')
    ->name('brochure.request');

Route::get('/brochure/download', [BrochureController::class, 'download'])
    ->middleware('throttle:10,1')
    ->name('brochure.download');

/*
 * One front door. `/login` asks for an email and *then* decides what to ask
 * for next — a password for an account that exists, or an emailed code for one
 * that doesn't yet. There is no `/register`: see LoginIdentifyController for
 * the three-way branch and why the screen is built this way.
 *
 * `GET /login` and `POST /login` are deliberately absent from this group.
 * Both are Fortify's: the first renders the email field (see
 * FortifyServiceProvider → Fortify::loginView), the second authenticates a
 * password and carries the login throttle, session regeneration and the
 * two-factor handoff. Redeclaring either URI here would shadow Fortify's route
 * in the named-route lookup and reintroduce exactly the bug documented at the
 * top of this file for `verification.verify`.
 *
 * Every endpoint below is public and unauthenticated, so every one is
 * throttled — same reasoning as the newsletter and brochure routes above, plus
 * one more: the `verify` endpoints guess at a 6-digit secret, and the
 * per-code attempt counter in GeneratesEmailCodes is the backstop, not the
 * front line.
 */
Route::prefix('login')->name('login.')->group(function () {
    Route::post('/identify', [LoginIdentifyController::class, 'identify'])
        ->middleware('throttle:10,1')
        ->name('identify');

    Route::get('/password', [LoginIdentifyController::class, 'showPassword'])->name('password');

    // "Email me a code instead" — passwordless sign-in for an existing
    // account. Refused for two-factor and banned accounts; see the service.
    Route::post('/code', [LoginCodeController::class, 'send'])
        ->middleware('throttle:5,1')
        ->name('code.send');

    Route::get('/code', [LoginCodeController::class, 'show'])->name('code');

    Route::post('/code/verify', [LoginCodeController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('code.verify');

    // ── Finishing a signup ──────────────────────────────────────────────
    Route::get('/verify', [RegistrationController::class, 'showVerify'])->name('verify');

    Route::post('/verify', [RegistrationController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('verify.store');

    Route::post('/resend', [RegistrationController::class, 'resend'])
        ->middleware('throttle:5,1')
        ->name('resend');

    Route::get('/details', [RegistrationController::class, 'showDetails'])->name('details');

    Route::post('/details', [RegistrationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('details.store');
});

/*
 * "Continue with Google / Facebook" — the buttons under the email field.
 *
 * Outside the /login prefix because the provider redirects back to a fixed,
 * registered URI and those are easier to keep stable when they are not nested
 * under a flow that may be restructured.
 *
 * `{provider}` is constrained to the two names this app actually supports, so
 * an unknown driver 404s at the router rather than reaching Socialite —
 * SocialAuthController re-checks anyway, since a route constraint permits a
 * provider that has no credentials configured.
 *
 * Throttled: the redirect leg is unauthenticated and cheap to spam, and the
 * callback leg triggers an outbound token exchange with the provider.
 */
Route::prefix('auth/{provider}')
    ->whereIn('provider', SocialAuthService::PROVIDERS)
    ->middleware('throttle:20,1')
    ->group(function () {
        Route::get('/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
        Route::get('/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
    });

/*
 * The two non-shopper doors. Off the /login prefix on purpose —
 * `/login/seller` would read as "sign in as a seller", which is the opposite
 * of what these are: they are where someone goes to apply for an account they
 * do not have.
 *
 * `/sell` is a real signup now. It asks for an email and hands off to the same
 * code flow /login uses, tagged with the seller intent — see
 * SellerRegistrationController. Throttled like every other unauthenticated
 * endpoint that costs an email.
 *
 * `/business` is still a holding page: `professional_buyer` decides which
 * price list an account is billed on, so it is deliberately impossible to
 * self-assign and needs its vetting step designed rather than bolted on.
 */
Route::get('/sell', [SellerRegistrationController::class, 'show'])->name('sell');

Route::post('/sell', [SellerRegistrationController::class, 'identify'])
    ->middleware('throttle:10,1')
    ->name('sell.identify');

Route::get('/business', [AccountKindController::class, 'business'])->name('business');

Route::post('/locale/{locale}', function (string $locale) {
    if (! in_array($locale, ['ar', 'fr', 'en'])) {
        abort(400);
    }
    session(['locale' => $locale]);

    return back();
})->name('locale.switch');

require __DIR__.'/settings.php';
require __DIR__.'/email-previews.php';

/*
 * Catch-all for URLs that match nothing above. Must stay last — a fallback
 * registered earlier would swallow every route declared after it.
 *
 * It exists so the 404 page gets the shared Inertia props. An unmatched URL
 * never enters the `web` group (the router throws before route middleware
 * runs), so HandleInertiaRequests never fires and the page would render with
 * no `translations`, `locale` or `auth` — meaning `t()` prints raw keys like
 * `common.errors.404.title` onto the screen and the header has no user.
 * Routing through here runs the group first, then aborts, so the exception
 * handler in bootstrap/app.php renders with everything present.
 */
Route::fallback(fn () => abort(404));
