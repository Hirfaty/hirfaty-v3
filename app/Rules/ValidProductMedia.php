<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Enforces per-type size (and, for images, pixel-dimension) limits on
 * product media uploads.
 *
 * Meant to run alongside the `mimes:` rule, which already restricts which
 * extensions are accepted — this rule adds the size/dimension guard that a
 * single flat `max:` can't express well (generous enough for video, it
 * would let a 50 MB image through; strict enough for images, it would
 * reject every real video).
 *
 * Limits live in config/media.php, the single source of truth also read by
 * the shop logo/banner validation in Admin\UserController.
 */
class ValidProductMedia implements ValidationRule
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const VIDEO_EXTENSIONS = ['mp4', 'mov'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('Fichier invalide.');

            return;
        }

        // Guess from the real MIME type rather than trusting the client's
        // filename extension.
        $extension = strtolower($value->extension() ?: $value->getClientOriginalExtension());
        $sizeKb = $value->getSize() / 1024;

        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            $maxKb = config('media.image.max_kb');

            if ($sizeKb > $maxKb) {
                $fail('L\'image "'.$value->getClientOriginalName().'" dépasse '.$this->formatMb($maxKb).'.');

                return;
            }

            $dimensions = @getimagesize($value->getRealPath());

            if ($dimensions) {
                [$width, $height] = $dimensions;
                $maxWidth = config('media.image.max_width');
                $maxHeight = config('media.image.max_height');

                if ($width > $maxWidth || $height > $maxHeight) {
                    $fail('L\'image "'.$value->getClientOriginalName().'" dépasse la résolution maximale de '.$maxWidth.'x'.$maxHeight.'px.');
                }
            }

            return;
        }

        if (in_array($extension, self::VIDEO_EXTENSIONS, true)) {
            $maxKb = config('media.video.max_kb');

            if ($sizeKb > $maxKb) {
                $fail('La vidéo "'.$value->getClientOriginalName().'" dépasse '.$this->formatMb($maxKb).'.');
            }

            return;
        }

        $fail('Type de fichier non supporté.');
    }

    private function formatMb(int $kb): string
    {
        return round($kb / 1024, 1).' Mo';
    }
}
