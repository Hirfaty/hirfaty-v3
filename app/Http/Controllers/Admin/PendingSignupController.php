<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Services\AdminUserListService;
use App\Services\RegistrationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * People who started signing up and stopped before choosing a password.
 *
 * Not users yet — there is no `users` row until the last step — so they get
 * their own page next to the users table rather than a role tab inside it.
 */
class PendingSignupController extends Controller
{
    public function index(Request $request, AdminUserListService $list): Response
    {
        $search = $request->string('search')->toString() ?: null;

        return Inertia::render('admin/users/pending', [
            'signups' => $list->pendingSignups($search)
                ->paginate(20)
                ->withQueryString()
                ->through(fn (PendingRegistration $pending) => $list->presentPendingSignup($pending)),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Mail the visitor a fresh code and confirm link, so they can pick the
     * signup back up — from any device, thanks to the link.
     */
    public function resend(PendingRegistration $pending, RegistrationCodeService $codes): RedirectResponse
    {
        if (! $codes->resend($pending)) {
            return back()->with('error', 'Un e-mail vient déjà de partir. Réessayez dans '
                .$codes->secondsUntilResend($pending).' secondes.');
        }

        return back()->with('success', "Nouveau lien envoyé à {$pending->email}.");
    }
}
