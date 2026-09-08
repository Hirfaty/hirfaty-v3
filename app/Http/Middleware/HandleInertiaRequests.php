<?php

namespace App\Http\Middleware;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                // Admin-generated password reset link, passed back to the page
                // so it can be displayed for copying rather than emailed.
                // Only ever set by Admin\UserController::resetLink; null on
                // every other response.
                'reset_link' => session('reset_link'),
            ],
            'locale' => app()->getLocale(),
            'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            'translations' => $this->getTranslations(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'cart' => fn () => $this->getSharedCart($request),
        ];
    }

    private function getTranslations(): array
    {
        $locale = app()->getLocale();
        $files = ['common', 'seller', 'buyer', 'admin', 'header', 'welcome', 'about', 'pro'];
        $translations = [];

        foreach ($files as $file) {
            $path = lang_path("{$locale}/{$file}.php");
            if (file_exists($path)) {
                $translations[$file] = require $path;
            }
        }

        return $translations;
    }

    private function getSharedCart(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return ['items' => [], 'count' => 0, 'subtotal' => 0];
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $cart->load([
            'items.product.store',
            'items.product.images',
            'items.variant.options',
            'items.variant.media',
        ]);

        /*
         * Same price list the checkout will actually bill, resolved once.
         * This used to read `sale_price ?: base_price` directly, which
         * ignored both the trade price list and any variant override — the
         * header cart could therefore quote a total the checkout disagreed
         * with. Route through the line, like CheckoutService does.
         */
        $audience = Product::audienceFor($user);

        $items = $cart->items->map(function ($item) use ($audience) {
            /*
             * Null when the product has no agreed price yet. Shown as 0 in the
             * header rather than crashing the layout; checkout refuses the
             * line outright, so this is only ever a display placeholder for a
             * basket that cannot be paid for as it stands.
             */
            $unit = $item->unitPriceFor($audience) ?? 0.0;
            $qty = (int) $item->quantity;

            /*
             * The chosen variant's own photo wins when the seller uploaded
             * one — same precedence the product page's gallery uses — so a
             * "Rouge" line in the header shows red, not whatever colour
             * happens to be the product's first image. Falls back to the
             * product's own first image, then null (the header renders a
             * placeholder rather than an empty <img>).
             */
            $image = $item->variant?->media->first()?->url
                ?? $item->product?->images->first()?->url;

            return [
                'id' => $item->id,
                'name' => $item->product?->name ?? 'Produit',
                // e.g. "Rouge / M" — null for a product without variants, so
                // the header can render it only when there is one.
                'variant' => $item->variantLabel(),
                'image' => $image,
                'seller' => $item->product?->store?->store_name ?? '',
                'qty' => $qty,
                'price' => $unit,
                'line_total' => $unit * $qty,
            ];
        })->values()->all();

        return [
            'items' => $items,
            'count' => collect($items)->sum('qty'),
            'subtotal' => collect($items)->sum('line_total'),
        ];
    }
}
