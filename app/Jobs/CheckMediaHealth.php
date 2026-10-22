<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\MediaHealthService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One batch of the admin's "Vérifier maintenant" image check — see
 * MediaHealthService::queueScan. A batch reads in parallel, so it finishes
 * within one read timeout however many of its images are lost.
 */
class CheckMediaHealth implements ShouldQueue
{
    use Queueable;

    /** A re-run would only re-read the same files; the next scan will. */
    public int $tries = 1;

    /** @param  list<int>  $mediaIds */
    public function __construct(public array $mediaIds) {}

    public function handle(MediaHealthService $health): void
    {
        $media = Media::query()->whereKey($this->mediaIds)->where('type', 'image')->get();

        if ($media->isNotEmpty()) {
            $health->check($media);
        }
    }
}
