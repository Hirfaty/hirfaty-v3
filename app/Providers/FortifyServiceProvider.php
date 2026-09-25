<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse as CustomLoginResponse;
use App\Http\Responses\VerifyEmailResponse as CustomVerifyEmailResponse;
use App\Services\PostLoginRedirectService;
use App\Services\RegistrationCodeService;
use App\Services\SocialAuthService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as ContractsLoginResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as ContractsVerifyEmailResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Was `$this->app->singleton(VerifyEmailResponse::class,
         * CustomVerifyEmailResponse::class)` — both names aliased the same
         * App\Http\Responses class, so this bound that class to itself and
         * never touched Fortify's actual contract. Fortify kept using its own
         * default response (redirect to config('fortify.home'), i.e.
         * /dashboard, for every role) regardless of what CustomVerifyEmailResponse
         * did.
         */
        $this->app->singleton(ContractsVerifyEmailResponse::class, CustomVerifyEmailResponse::class);
        $this->app->singleton(ContractsLoginResponse::class, CustomLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        /*
         * No `Fortify::createUsersUsing()`. Registration is not a Fortify
         * feature here any more (see config/fortify.php) — accounts are
         * created by RegistrationCodeService::complete once an emailed code
         * has proven the address, which is a precondition Fortify's
         * CreatesNewUsers contract has no way to express.
         */
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        /*
         * The one front door: an email field, and nothing else.
         *
         * What happens next is decided server-side by
         * Auth\LoginIdentifyController::identify — password for an account that
         * exists, an emailed code for one that doesn't. There is no separate
         * signup screen, so no `canRegister` prop: the same field serves both.
         *
         * `resume` tells the page a signup is already in progress in this
         * browser, so it can *offer* to continue instead of silently
         * redirecting — someone arriving here may genuinely want to use a
         * different address, and deciding that for them is how you trap
         * people.
         */
        Fortify::loginView(function (Request $request) {
            // A public page sent them here (add to cart, the heart) and says where
            // to come back to. See PostLoginRedirectService for why it is sanitised.
            app(PostLoginRedirectService::class)->remember($request, $request->query('redirect'));

            $pending = app(RegistrationCodeService::class)->resolve($request);

            return Inertia::render('auth/login/identify', [
                'status' => $request->session()->get('status'),
                'resume' => $pending ? [
                    'maskedEmail' => $pending->maskedEmail(),
                    'step' => $pending->nextStep(),
                ] : null,
                /*
                 * Only the providers that actually have credentials, so the
                 * screen never renders a button that would bounce the visitor
                 * to an OAuth error page. Empty on a fresh checkout, which the
                 * page treats as "no social sign-in" and hides the divider with
                 * it — see pages/auth/login/identify.tsx.
                 */
                'socialProviders' => app(SocialAuthService::class)->configuredProviders(),
            ]);
        });

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        // No `Fortify::registerView()` — see the login view above.

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
