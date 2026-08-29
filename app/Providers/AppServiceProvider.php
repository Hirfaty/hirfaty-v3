<?php

namespace App\Providers;

use App\Health\Checks\DiskSpaceCheck;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
        $this->configureHealthChecks();
        JsonResource::withoutWrapping();
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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
