<?php

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
