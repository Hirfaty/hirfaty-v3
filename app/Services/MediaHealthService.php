<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Finds product, variant and blog images whose stored file can no longer be
 * read, and stamps them `broken_at` so the admin products list can filter on
 * it (AdminProductListService, "Images cassées").
 *
 * Every image is actually downloaded, not just stat'ed: after the October 2026
 * SeaweedFS incident the filer still reported each lost file's size, so only
 * reading the bytes tells a lost photo from a healthy one.
 */
class MediaHealthService
{
    /** A lost SeaweedFS object stalls ~13s before failing; don't wait that long. */
    private const TIMEOUT_SECONDS = 10;

    /** @var array<string, Filesystem> */
    private array $disks = [];

    /**
     * Check every image and record the result. A file that reads again (it was
     * re-uploaded under the same key, say) is cleared; one that was already
     * broken keeps the date it was first found broken.
     *
     * @param  (callable(Media, bool): void)|null  $progress
     * @return array{checked: int, broken: int}
     */
    public function scan(?callable $progress = null): array
    {
        $checked = 0;
        $broken = 0;

        Media::query()->where('type', 'image')->lazyById(200)->each(
            function (Media $media) use (&$checked, &$broken, $progress) {
                $healthy = $this->isReadable($media);
                $checked++;

                if (! $healthy) {
                    $broken++;
                }

                if ($healthy && $media->broken_at !== null) {
                    $media->forceFill(['broken_at' => null])->save();
                } elseif (! $healthy && $media->broken_at === null) {
                    $media->forceFill(['broken_at' => now()])->save();
                }

                if ($progress) {
                    $progress($media, $healthy);
                }
            },
        );

        return ['checked' => $checked, 'broken' => $broken];
    }

    public function isReadable(Media $media): bool
    {
        try {
            $contents = $this->disk($media->disk)->get($media->path);
        } catch (Throwable) {
            return false;
        }

        return is_string($contents) && $contents !== '';
    }

    /**
     * S3-backed disks are rebuilt with a request timeout, so a stalled object
     * costs seconds instead of the SDK's unbounded default. Local disks are
     * used as configured (which also keeps Storage::fake working in tests).
     */
    private function disk(string $name): Filesystem
    {
        if (isset($this->disks[$name])) {
            return $this->disks[$name];
        }

        $config = config("filesystems.disks.{$name}");

        return $this->disks[$name] = ($config['driver'] ?? null) === 's3'
            ? Storage::build([
                ...$config,
                'throw' => true,
                'http' => ['timeout' => self::TIMEOUT_SECONDS, 'connect_timeout' => 5],
            ])
            : Storage::disk($name);
    }
}
