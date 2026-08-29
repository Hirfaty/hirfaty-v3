# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**Hirfaty** is a multi-vendor marketplace built on the Laravel + React starter kit: Laravel 13 (PHP 8.3) backend, Inertia v3 + React 19 + TypeScript frontend, Tailwind v4 + shadcn/ui components. There is one SPA-like app with three distinct user roles — `super_admin`, `seller`, `buyer` — each with its own route group, controllers, and page tree.

## Commands

Run from the repo root. On Windows the shell is PowerShell.

- **Dev (everything at once):** `composer dev` — runs `php artisan serve`, the queue listener, and `npm run dev` (Vite) concurrently. This is the normal way to work; the queue listener matters because notifications/emails are queued.
- **Frontend only:** `npm run dev` · **Build:** `npm run build` · **SSR build:** `npm run build:ssr`
- **Tests (Pest):** `php artisan test` · single file: `php artisan test tests/Feature/Buyer/CheckoutFlowTest.php` · single test by name: `php artisan test --filter="it processes checkout"`
- **PHP lint/format (Pint):** `composer lint` (writes) / `composer lint:check` (dry run)
- **JS/TS lint:** `npm run lint` (writes) / `npm run lint:check` · **format:** `npm run format` / `npm run format:check` · **types:** `npm run types:check`
- **Full CI gate locally:** `composer ci:check` (JS lint + format + types + PHP tests). `composer test` runs Pint check then the PHP suite.

Tests run on in-memory SQLite (see `phpunit.xml`); mail/queue are synchronous/array-backed in the test env.

## Wayfinder — typed routes (do not hand-write URLs)

Route helpers and controller actions under `resources/js/routes/` and `resources/js/actions/` are **generated** by `@laravel/vite-plugin-wayfinder` from `routes/*.php` and the controllers. Import from these instead of hardcoding paths, e.g. `import ProductController from '@/actions/App/Http/Controllers/Seller/ProductController'`. After changing PHP routes or controller signatures, the Vite plugin regenerates them on the next dev run/build — don't edit files under `routes/` or `actions/` by hand.

## Request/response flow

1. Routes in `routes/web.php` (plus `routes/settings.php`, `routes/email-previews.php`) are grouped by middleware:
    - `role:super_admin` → `/admin/*`, `Admin\*` controllers, `pages/admin/*`
    - `role:seller` + `store.setup` → `/seller/*`, `Seller\*` controllers, `pages/seller/*`
    - `role:buyer` → cart/checkout/orders/wishlist, `Buyer\*` controllers, `pages/buyer/*`
    - Public: product listing/detail (`/products`, `/products/{slug}`) work unauthenticated.
2. `RoleMiddleware` (alias `role`) hard-checks `$user->role` against a single string and 403s otherwise. `EnsureStoreIsSetup` (alias `store.setup`) redirects a seller with no `Store` to `seller.setup.store`.
3. Controllers return `Inertia::render('page/path', [...])`, resolving to `resources/js/pages/<page/path>.tsx`.
4. `HandleInertiaRequests::share()` injects globally on every response: `auth.user`, `flash.{success,error}`, `locale`, `direction`, `translations`, and a lazily-evaluated `cart` (server-side cart summary). New global props go here.

Note: `resources/js/app.tsx` sets Inertia's `layout` to `null` — pages import and wrap their own layouts (e.g. from `resources/js/layouts/`), there is no automatic per-page layout resolution.

## Roles & auth

- Auth is **Laravel Fortify** (registration, login, password reset, email verification, 2FA), wired in `FortifyServiceProvider`. `User` has role helpers (`isSeller()`, `isBuyer()`, `isSuperAdmin()`) and role is a plain string column, not a package/enum.
- Email verification is role-aware: `User::sendEmailVerificationNotification()` sends `SellerVerifyEmail` vs `BuyerVerifyEmail`; after verifying, sellers are redirected to store setup (`routes/web.php`).

## Internationalization (ar / fr / en, with RTL)

- Translations live in `lang/<locale>/{common,seller,buyer,header,welcome}.php` as **flat** PHP arrays whose keys may contain dots (`'how_it_works.step1.title' => '…'`) — **not** nested arrays. `useTranslation()` (`resources/js/hooks/use-translation.tsx`) splits a key once on the first dot to pick the file, then does a flat lookup on the remainder. A genuinely nested array will therefore never resolve, and `t()` silently renders the raw key onto the page.
- `HandleInertiaRequests::getTranslations()` has a hardcoded file list; a new `lang/<locale>/<file>.php` is ignored until it is added there, and a missing file is skipped silently. All three locales must define the same keys.
- Locale is stored in the session (`SetLocale` middleware) and switched via `POST /locale/{locale}`. Arabic sets `direction: 'rtl'`; account for RTL when doing layout work. Arabic also relies on the `[lang='ar']` / `[dir='rtl']` overrides in `app.css`: Fraunces and Inter carry no Arabic glyphs, and Arabic must never be italicised.

## Cart & checkout

- **Guest cart** lives in `localStorage` under `hirfaty_guest_cart`, managed by `CartContext`/`useGuestCart` (`resources/js/contexts/CartContext.tsx`), wrapping the whole app in `app.tsx`. On login it is merged into the DB cart via `POST /cart/merge` (see `CartSync`/`CartContext` usage).
- **Server cart** is a `Cart` hasMany `CartItem`; `CheckoutService::process()` is the money path. It: locks product rows (`lockForUpdate`) to validate stock, **splits one cart into multiple `Order`s grouped by store**, computes `platform_fee` (20%, `PLATFORM_FEE_PERCENT`) plus a flat `AMANA_FEE` (20) per order, writes an `Earning` per store, decrements stock, notifies seller + buyer, and clears the cart — all in one DB transaction. Change fee logic only in this service.

## Products, media, moderation

- `Product` belongs to a `Store` (a seller has one `Store`) and a `Category`. Images/videos are **polymorphic** via `Media` (`morphMany 'mediable'`, ordered by `order`; `images()`/`videos()` filter by `type`).
- Products move through a moderation lifecycle by `status` string: `pending_review` → `published` / `rejected` / `archived` (helpers `isPendingReview()` etc.). Admins approve/reject/archive via `Admin\ProductController`; rejection stores `rejection_reason` and notifies the seller (`ProductApproved`/`ProductRejected`).

## Conventions to follow

- **API responses:** shape data for the frontend with `App\Http\Resources\*` (`BuyerProductResource`, etc.) rather than passing raw models into Inertia props.
- **Notifications & emails:** user-facing mail is queued `Notification` classes in `app/Notifications/`; Blade email templates live under `resources/views/emails/`. `routes/email-previews.php` exposes preview routes for them. Because mail is queued, the queue listener (part of `composer dev`) must run to actually send.
- **Business logic** that spans models goes in `app/Services/` (`CheckoutService`, `ProductScoringService`), not controllers.
- **UI primitives** are shadcn/ui in `resources/js/components/ui/`; compose these rather than adding new component libraries.
- React Compiler is enabled (`babel-plugin-react-compiler` in `vite.config.ts`) — avoid manual `useMemo`/`useCallback` micro-optimizations that fight it.
- any code in in controller should be organized inside services folder
