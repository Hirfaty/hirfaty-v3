<?php

namespace App\Http\Controllers;

use App\Http\Resources\BuyerProductResource;
use App\Services\HomeService;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HomeController extends Controller
{
    public function __invoke(HomeService $home): Response
    {
        return Inertia::render('welcome', [
            'canRegister' => Features::enabled(Features::registration()),
            'featuredProducts' => BuyerProductResource::collection(
                $home->featuredProducts(),
            ),
        ]);
    }
}
