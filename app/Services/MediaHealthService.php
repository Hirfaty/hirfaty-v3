<?php

namespace App\Services;

use App\Jobs\CheckMediaHealth;
use App\Models\Media;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Finds product, variant and blog images whose stored file can no longer be
 * read, and stamps them `broken_at` so the admin products list can filter on
 * it (AdminProductListService, "Images cassées").
 *
 * Every image is actually downloaded, not just stat'ed: after the October 2026
 * SeaweedFS incident the filer still reported each lost file's size, then
 * stalled ~13s and closed the connection, so only reading the bytes tells a
 * lost photo from a healthy one. S3-backed images are fetched in parallel
 * through presigned URLs, with no retries — through the S3 SDK, each lost
 * photo cost four timed-out attempts and a full scan ran for hours.
 */
class MediaHealthService
{
    /** Images read at once, and per queued job. */
    public const BATCH = 20;

    private const TIMEOUT_SECONDS = 10;

    /**
     * Check every image now, in this process (the `media:check` command).
     *
     * @param  (callable(Media, bool): void)|null  $progress
     * @return array{checked: int, broken: int}
     */
    public function scan(?callable $progress = null): array
    {
        $checked = 0;
        $broken = 0;

        Media::query()->where('type', 'image')->chunkById(self::BATCH, function (Collection $batch) use (&$checked, &$broken, $progress) {
            foreach ($this->check($batch) as $id => $healthy) {
                $checked++;
                $broken += $healthy ? 0 : 1;

                if ($progress) {
                    $progress($batch->firstWhere('id', $id), $healthy);
                }
            }
        });

        return ['checked' => $checked, 'broken' => $broken];
    }

    /**
     * The admin's "Vérifier maintenant": the same check, spread over small
     * queued jobs so none outlives the queue's retry window.
     *
     * @return int how many images were queued
     */
    public function queueScan(): int
    {
        $ids = Media::query()->where('type', 'image')->orderBy('id')->pluck('id');

        $ids->chunk(self::BATCH)->each(
            fn (Collection $chunk) => CheckMediaHealth::dispatch($chunk->values()->all()),
        );

        return $ids->count();
    }

    /**
     * Read these images and record each result. A file that reads again (it
     * was re-uploaded under the same key, say) is cleared; one that was
     * already broken keeps the date it was first found broken.
     *
     * @param  Collection<int, Media>  $media
     * @return array<int, bool> healthy, by media id
     */
    public function check(Collection $media): array
    {
        $results = $this->readable($media);

        foreach ($media as $item) {
            $healthy = $results[$item->id];

            $item->forceFill([
                'checked_at' => now(),
                'broken_at' => $healthy ? null : ($item->broken_at ?? now()),
            ])->save();
        }

        return $results;
    }

    /**
     * @param  Collection<int, Media>  $media
     * @return array<int, bool>
     */
    private function readable(Collection $media): array
    {
        $results = [];
        $urls = [];

        foreach ($media as $item) {
            if (! $this->isS3($item->disk)) {
                $results[$item->id] = $this->readsLocally($item);

                continue;
            }

            try {
                $urls[$item->id] = Storage::disk($item->disk)->temporaryUrl($item->path, now()->addMinutes(15));
            } catch (Throwable) {
                $results[$item->id] = false;
            }
        }

        if ($urls === []) {
            return $results;
        }

        $responses = Http::pool(fn (Pool $pool) => collect($urls)->map(
            fn (string $url, int $id) => $pool->as((string) $id)
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(5)
                ->get($url),
        )->values()->all(), self::BATCH);

        foreach (array_keys($urls) as $id) {
            $results[$id] = $this->isComplete($responses[$id] ?? $responses[(string) $id] ?? null);
        }

        return $results;
    }

    /** A truncated body surfaces as a ConnectionException, not a Response. */
    private function isComplete(mixed $response): bool
    {
        if (! $response instanceof Response || ! $response->successful()) {
            return false;
        }

        $body = $response->body();
        $length = $response->header('Content-Length');

        return $body !== '' && ($length === '' || strlen($body) === (int) $length);
    }

    private function readsLocally(Media $media): bool
    {
        try {
            $contents = Storage::disk($media->disk)->get($media->path);
        } catch (Throwable) {
            return false;
        }

        return is_string($contents) && $contents !== '';
    }

    private function isS3(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 's3';
    }
}
