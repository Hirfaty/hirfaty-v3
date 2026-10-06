<?php

use App\Services\PaymentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Commands\DispatchQueueCheckJobsCommand;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Commands\ScheduleCheckHeartbeatCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Health monitoring ───────────────────────────────────────────────
// Runs the checks registered in AppServiceProvider and stores the results,
// which the admin dashboard reads. Requires `php artisan schedule:work`
// (or a cron entry) to be running.
Schedule::command(RunHealthChecksCommand::class)->everyMinute();

// ScheduleCheck only passes while this heartbeat keeps getting written, so a
// dead scheduler surfaces as a failing check instead of silently stale results.
Schedule::command(ScheduleCheckHeartbeatCommand::class)->everyMinute();

// QueueCheck works the same way: this pushes a job the queue worker must pick
// up, proving the worker is actually consuming jobs.
Schedule::command(DispatchQueueCheckJobsCommand::class)->everyMinute();

/*
 * ── Exchange rates ──────────────────────────────────────────────────
 *
 * Warms the rate cache so no buyer ever waits on the rates API.
 * ExchangeRateService fetches lazily if this hasn't run, and falls back to
 * config/currency.php if the fetch fails, so a missed run degrades the
 * freshness of a displayed price and nothing more.
 *
 * Early morning, before Moroccan traffic picks up. Daily is plenty: these
 * figures are indicative, and prices settle in MAD regardless.
 */
Schedule::command('currency:refresh-rates')->dailyAt('04:30');

/*
 * ── Unpaid card purchases ───────────────────────────────────────────
 *
 * A card checkout holds its stock while the buyer is on Payzone's page. One
 * nobody pays for within PAYZONE_EXPIRE_AFTER_MINUTES is cancelled and its
 * stock handed back — see PaymentService::expireStale.
 */
Artisan::command('payments:expire-stale', function (PaymentService $payments) {
    $this->info($payments->expireStale().' unpaid card purchase(s) released.');
})->purpose('Release card purchases nobody paid for in time');

Schedule::command('payments:expire-stale')->everyFiveMinutes()->withoutOverlapping();
