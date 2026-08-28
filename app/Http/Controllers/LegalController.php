<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class LegalController extends Controller
{
    /**
     * Public "Terms of Use" page (linked from registration, footer, etc.).
     */
    public function terms()
    {
        return Inertia::render('legal/terms');
    }

    /**
     * Public "Privacy Policy" page.
     */
    public function privacy()
    {
        return Inertia::render('legal/privacy');
    }

    /**
     * Blocking "accept the terms" page — anyone EnsureTermsAccepted redirects here.
     */
    public function acceptShow()
    {
        return Inertia::render('legal/accept-terms');
    }

    /**
     * Record acceptance and send the user back where they were headed.
     */
    public function acceptStore(Request $request)
    {
        $request->validate([
            'terms_accepted' => ['required', 'accepted'],
        ]);

        $request->user()->update([
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);

        return redirect()->intended(route('dashboard'));
    }
}
