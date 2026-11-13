<?php

namespace App\Http\Controllers;

use App\Services\PriceModeService;
use Illuminate\Http\RedirectResponse;

/**
 * The header's "Particulier / Pro" switch and the first-visit chooser.
 *
 * Display only — see PriceModeService. Nothing a visitor posts here changes
 * what they are billed.
 */
class PriceModeController extends Controller
{
    public function __invoke(string $mode, PriceModeService $modes): RedirectResponse
    {
        // The route already constrains {mode}; this is the second fence.
        abort_unless($modes->remember($mode), 404);

        return back();
    }
}
