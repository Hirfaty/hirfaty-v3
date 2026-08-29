<?php

namespace App\Health\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Disk usage of the partition holding the application.
 *
 * Spatie ships a `UsedDiskSpaceCheck`, but it shells out to `df -P`, which
 * doesn't exist on Windows — it crashes on a local dev machine and only works
 * on the Linux server. `disk_free_space()`/`disk_total_space()` are built into
 * PHP and behave the same on both, so the check is meaningful everywhere.
 */
class DiskSpaceCheck extends Check
{
    protected int $warningThreshold = 70;

    protected int $errorThreshold = 90;

    protected ?string $path = null;

    public function warnWhenUsedSpaceIsAbovePercentage(int $percentage): self
    {
        $this->warningThreshold = $percentage;

        return $this;
    }

    public function failWhenUsedSpaceIsAbovePercentage(int $percentage): self
    {
        $this->errorThreshold = $percentage;

        return $this;
    }

    public function path(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function run(): Result
    {
        $path = $this->path ?? base_path();

        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        // Both return false on failure (unreadable path, restricted host); a
        // zero total would also blow up the division below.
        if ($total === false || $free === false || $total <= 0) {
            return Result::make()->failed('Could not determine disk usage.');
        }

        $usedPercentage = (int) round((($total - $free) / $total) * 100);

        $result = Result::make()
            ->meta([
                'disk_space_used_percentage' => $usedPercentage,
                'free_gb' => round($free / 1024 ** 3, 1),
                'total_gb' => round($total / 1024 ** 3, 1),
            ])
            ->shortSummary($usedPercentage.'%');

        if ($usedPercentage > $this->errorThreshold) {
            return $result->failed("The disk is almost full ({$usedPercentage}% used).");
        }

        if ($usedPercentage > $this->warningThreshold) {
            return $result->warning("The disk is almost full ({$usedPercentage}% used).");
        }

        return $result->ok();
    }
}
