<?php

namespace App\Http\Middleware;

use App\Models\Cart;
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
        $files = ['common', 'seller', 'buyer', 'admin', 'header', 'welcome'];
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

        // $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        // $cart->load(['items.product.store']);

        // $items = $cart->items->map(function ($item) {
        //     $unit = (float) ($item->product?->sale_price ?: $item->product?->base_price ?: 0);
        //     $qty = (int) $item->quantity;

        //     return [
        //         'id' => $item->id,
        //         'name' => $item->product?->name ?? 'Produit',
        //         'seller' => $item->product?->store?->store_name ?? '',
        //         'qty' => $qty,
        //         'price' => $unit,
        //         'line_total' => $unit * $qty,
        //     ];
        // })->values()->all();

        return [
            // 'items' => $items,
            // 'count' => collect($items)->sum('qty'),
            // 'subtotal' => collect($items)->sum('line_total'),
        ];
    }
}
