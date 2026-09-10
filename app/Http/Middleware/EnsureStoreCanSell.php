<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the routes that put a product in front of buyers until the shop is
 * both complete and approved (see Store::canSell()).
 *
 * Middleware rather than a check inside ProductController because the create
 * screen and the POST that backs it are two separate entry points; guarding
 * only the screen would leave a hand-rolled POST able to publish from an
 * unreviewed shop.
 *
 * Redirects rather than 403s: this is a "not yet" for a seller who has done
 * nothing wrong, and the products list carries the status bar explaining
 * exactly what is outstanding.
 */
class EnsureStoreCanSell
{
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->user()?->store;

        if ($store && ! $store->canSell()) {
            return redirect()
                ->route('seller.products.index')
                ->with('error', $this->reasonFor($store));
        }

        return $next($request);
    }

    private function reasonFor(Store $store): string
    {
        if (! $store->hasCompleteProfile()) {
            return 'Complétez les informations de votre atelier avant d’ajouter un produit.';
        }

        if ($store->isRejected()) {
            return 'Votre atelier n’a pas été validé. Corrigez les points signalés, puis renvoyez-le en validation.';
        }

        return 'Votre atelier est en cours de validation. Vous pourrez ajouter des produits dès qu’il sera approuvé.';
    }
}
