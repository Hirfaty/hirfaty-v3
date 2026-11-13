<?php

namespace App\Http\Controllers;

use App\Http\Resources\BlogResource;
use App\Http\Resources\BuyerProductResource;
use App\Services\HomeService;
use App\Services\NewArrivalsService;
use App\Services\PriceModeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(
        Request $request,
        HomeService $home,
        NewArrivalsService $newArrivals,
        PriceModeService $priceMode,
    ): Response {
        // "Particulier" or "pro", as the visitor chose to browse — decides
        // which pieces both rows list (PriceModeService).
        $audience = $priceMode->audience($request);

        return Inertia::render('welcome', [
            // No `canRegister`: signup is not a separate screen any more, so
            // there is nothing for the home page to conditionally offer. The
            // sign-in link doubles as the signup link.
            'featuredProducts' => BuyerProductResource::collection(
                $home->featuredProducts($audience),
            ),
            /*
             * Eighteen (the service's own default) — the row below the
             * showcase holds three slides of six. Passed through the same
             * service the /new-arrivals page uses, so "newest" means the same
             * thing in both places — turns across categories, so one batch
             * cannot fill the row — and so does the audience gate, which is
             * what keeps a trade-only piece off a logged-out visitor's home
             * page.
             */
            'newArrivals' => BuyerProductResource::collection(
                $newArrivals->forHome($audience),
            ),
            // The three newest journal articles, below the brochure.
            'latestPosts' => BlogResource::collection($home->latestPosts()),
        ]);
    }
}
