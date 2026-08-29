<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Health\ResultStores\ResultStore;

/**
 * Reads the last stored health-check run for the admin dashboard.
 *
 * Checks are executed by the scheduled `health:check` command, never inline —
 * a request should never wait on a network ping or a disk stat. That means
 * results can be stale, so `checked_at`/`stale` are exposed and the UI says so
 * rather than presenting old data as current.
 */
class HealthReportService
{
    /** Results older than this are flagged; the schedule runs every minute. */
    private const STALE_AFTER_MINUTES = 5;

    public function __construct(private readonly ResultStore $resultStore) {}

    /**
     * @return array{
     *     status: string,
     *     checked_at: ?string,
     *     stale: bool,
     *     checks: array<int, array<string, mixed>>,
     *     counts: array<string, int>
     * }
     */
    public function report(): array
    {
        $latest = $this->resultStore->latestResults();

        // No run stored yet — a fresh install, or the scheduler never ran.
        if ($latest === null) {
            return [
                'status' => 'unknown',
                'checked_at' => null,
                'stale' => true,
                'checks' => [],
                'counts' => $this->countsFrom(collect()),
            ];
        }

        $checks = $latest->storedCheckResults
            ->map(fn ($result): array => [
                'name' => $result->name,
                'label' => $result->label ?: $result->name,
                'status' => $result->status,
                'summary' => $result->shortSummary,
                'message' => $result->notificationMessage,
                'meta' => $result->meta,
            ])
            ->values();

        $checkedAt = Carbon::instance($latest->finishedAt);

        return [
            'status' => $this->overallStatus($checks),
            'checked_at' => $checkedAt->toIso8601String(),
            'stale' => $checkedAt->lt(now()->subMinutes(self::STALE_AFTER_MINUTES)),
            'checks' => $checks->all(),
            'counts' => $this->countsFrom($checks),
        ];
    }

    /**
     * Worst status wins: any failure/crash turns the whole panel red, a warning
     * turns it amber. Skipped checks are intentional (production-only checks in
     * dev) and don't degrade the overall status.
     *
     * @param  Collection<int, array<string, mixed>>  $checks
     */
    private function overallStatus(Collection $checks): string
    {
        if ($checks->isEmpty()) {
            return 'unknown';
        }

        if ($checks->whereIn('status', ['failed', 'crashed'])->isNotEmpty()) {
            return 'failed';
        }

        return $checks->where('status', 'warning')->isNotEmpty() ? 'warning' : 'ok';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $checks
     * @return array<string, int>
     */
    private function countsFrom(Collection $checks): array
    {
        return [
            'ok' => $checks->where('status', 'ok')->count(),
            'warning' => $checks->where('status', 'warning')->count(),
            'failed' => $checks->where('status', 'failed')->count(),
            'crashed' => $checks->where('status', 'crashed')->count(),
            'skipped' => $checks->where('status', 'skipped')->count(),
        ];
    }
}
