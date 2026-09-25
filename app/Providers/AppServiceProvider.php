<?php

namespace App\Providers;

use App\Health\Checks\DiskSpaceCheck;
use App\Services\LocaleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\PingCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Facades\Health;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureLocaleUrls();
        $this->configureHealthChecks();
        JsonResource::withoutWrapping();
    }

    /**
     * Fill the `{locale}` segment of public routes automatically, so
     * route('products.show', $slug) needs no language argument anywhere.
     *
     * Follows the active locale rather than being set once: SetLocale changes
     * it per request, and a queued notification switches it to the
     * recipient's language while rendering (Laravel fires LocaleUpdated for
     * both), so an Arabic email links to /ar/… even when a French-speaking
     * admin triggered it. Ziggy and Wayfinder read the same default on the
     * client — see syncLocale() in resources/js/app.tsx.
     */
    protected function configureLocaleUrls(): void
    {
        $initial = app()->getLocale();

        URL::defaults([
            'locale' => in_array($initial, LocaleService::SUPPORTED, true) ? $initial : LocaleService::DEFAULT,
        ]);

        Event::listen(LocaleUpdated::class, function (LocaleUpdated $event) {
            if (in_array($event->locale, LocaleService::SUPPORTED, true)) {
                URL::defaults(['locale' => $event->locale]);
            }
        });
    }

    /**
     * Register the checks shown on the admin dashboard's health panel.
     *
     * Results are written to the `health_check_result_history_items` table by
     * `php artisan health:check` (scheduled every minute in routes/console.php)
     * — the dashboard only reads the last stored run, it never executes checks
     * inline, so a slow or hanging check can't stall the page.
     */
    protected function configureHealthChecks(): void
    {
        $mediaDisk = config('filesystems.default');

        Health::checks(array_filter([
            DatabaseCheck::new(),
            CacheCheck::new(),

            // The queue listener is part of `composer dev`; when it isn't
            // running, every queued notification/email silently stops going
            // out, which is otherwise invisible.
            QueueCheck::new(),

            ScheduleCheck::new(),

            DiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(70)
                ->failWhenUsedSpaceIsAbovePercentage(90),

            // Product images, videos and shop logos/banners all live in
            // SeaweedFS — only worth pinging when it's the active disk.
            $mediaDisk === 'seaweedfs'
                ? PingCheck::new()
                    ->name('SeaweedFS')
                    ->label('SeaweedFS')
                    ->url(rtrim((string) config('filesystems.disks.seaweedfs.endpoint'), '/').'/status')
                    ->timeout(5)
                    ->failureMessage('SeaweedFS is unreachable — media uploads and images will fail.')
                : null,

            // Both of these are expected to "fail" locally (debug on, nothing
            // cached), so they'd be permanent red noise outside production.
            DebugModeCheck::new()->if(app()->isProduction()),
            OptimizedAppCheck::new()->if(app()->isProduction()),
        ]));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        /*
         * The same complexity policy everywhere a password is set —
         * registration, "forgot password", and account settings all share
         * `passwordRules()` (see App\Concerns\PasswordValidationRules), so
         * this one rule is what actually gets enforced on each of them.
         *
         * Used to be production-only, which meant a one-character password
         * sailed through on every other environment while the register
         * screen's own hint text (common.auth.register.password_hint) kept
         * advertising the strict rules regardless — the policy is real
         * everywhere now, so the hint stops lying outside prod.
         *
         * `uncompromised()` stays production-only: it calls the k-anonymity
         * HaveIBeenPwned API over the network on every check, which would
         * make local dev and the test suite depend on internet access for
         * no benefit — nobody is registering a real leaked password in a
         * factory-seeded dev database.
         */
        Password::defaults(function (): Password {
            $rule = Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
