<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public page explaining pro prices (`/professional-account`).
 *
 * There is nothing to apply for: anyone shops on the pro list by switching to
 * "Pro" (PriceModeService), and the only condition is the minimum quantity
 * the admin set on each piece. The page explains that and offers the switch;
 * everything it needs comes from the shared `priceMode` prop.
 */
class ProfessionalRequestController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('buyer/professional-account');
    }
}
