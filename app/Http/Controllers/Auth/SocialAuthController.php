<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\LoginResponse;
use App\Services\BlockedAccountService;
use App\Services\SocialAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * "Continue with Google / Facebook" — the two buttons under the email field on
 * /login.
 *
 * Thin on purpose: the decisions all live in SocialAuthService. What this owns
 * is the round trip and its failure modes, of which there are more than the
 * happy path suggests — the visitor can decline consent, the provider can be
 * unreachable, and the callback can arrive for an address nobody has proved.
 * Every one of those has to come back as a sentence on the sign-in screen
 * rather than a stack trace.
 */
class SocialAuthController extends Controller
{
    public function __construct(
        private readonly SocialAuthService $social,
        private readonly BlockedAccountService $blocked,
    ) {}

    /**
     * Hand the visitor off to the provider.
     */
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $this->guard($provider);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * They came back. Work out who that is.
     */
    public function callback(Request $request, string $provider): Response
    {
        $this->guard($provider);

        /*
         * The visitor pressing "cancel" at Google is an ordinary outcome, not
         * an error — it arrives as `?error=access_denied` with no code. Saying
         * nothing at all is the right response: they chose to come back.
         */
        if ($request->has('error')) {
            return to_route('login');
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            /*
             * An expired/replayed code, a clock skew, a provider outage. Logged
             * because it is the only way to tell a misconfiguration from a
             * visitor with a stale tab, but never surfaced — the exception text
             * can carry client identifiers.
             */
            Log::warning("Social sign-in failed for [{$provider}]: ".$e->getMessage());

            return to_route('login')->with('error', __('common.auth.login.social_failed'));
        }

        $user = $this->social->resolveUser($provider, $socialUser);

        // No usable identity: no email, or one the provider would not vouch
        // for. Deliberately the same message either way — distinguishing them
        // would disclose which addresses exist here.
        if (! $user) {
            return to_route('login')->with('error', __('common.auth.login.social_unusable'));
        }

        /*
         * A ban has to hold on every door, and this one bypasses the password
         * screen entirely. Checked here rather than in the service so the
         * service stays a question about identity, not about permission.
         * Same notice page as every other door (BlockedAccountService).
         */
        if ($user->is_banned) {
            return $this->blocked->lockOut($request);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        /*
         * Reuses Fortify's response contract, so a social sign-in lands exactly
         * where a password or emailed-code sign-in would — admins on the
         * moderation queue, sellers in their shop, buyers in the market. Same
         * reasoning as LoginCodeController::verify.
         */
        return app(LoginResponse::class)->toResponse($request);
    }

    /**
     * Refuse anything not on the whitelist.
     *
     * A 404 rather than a redirect: `/auth/evil/redirect` is not a mistake a
     * real visitor makes, and handing it to Socialite would ask it to resolve a
     * driver this app never configured.
     */
    private function guard(string $provider): void
    {
        if (! $this->social->isConfigured($provider)) {
            throw new NotFoundHttpException;
        }
    }
}
