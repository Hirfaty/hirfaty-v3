<?php

namespace App\Http\Controllers;

use App\Http\Resources\BuyerProductResource;
use App\Services\HomeService;
use App\Services\NewArrivalsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HomeController extends Controller
{
    public function __invoke(
        Request $request,
        HomeService $home,
        NewArrivalsService $newArrivals,
    ): Response {
        return Inertia::render('welcome', [
            'canRegister' => Features::enabled(Features::registration()),
            'featuredProducts' => BuyerProductResource::collection(
                $home->featuredProducts(),
            ),
            /*
             * Eighteen (the service's own default) — the row below the
             * showcase holds three slides of six. Passed through the same
             * service the /new-arrivals page uses, so "newest" means the same
             * thing in both places — including the audience gate, which is
             * what keeps a trade-only piece off a logged-out visitor's home
             * page.
             */
            'newArrivals' => BuyerProductResource::collection(
                $newArrivals->forHome($request->user()),
            ),
        ]);
    }
}
