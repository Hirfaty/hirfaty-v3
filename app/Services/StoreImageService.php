<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The shop's logo and banner: the admin sets them when filing an account,
 * the seller can change the logo from their settings. One place for where
 * the files go and how big they may be.
 */
class StoreImageService
{
    /**
     * Validation for one logo/banner upload. Size and resolution caps come
     * from config/media.php, the same source product photos use.
     *
     * @return list<mixed>
     */
    public function rules(): array
    {
        return [
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:'.config('media.image.max_kb'),
            Rule::dimensions()
                ->maxWidth(config('media.image.max_width'))
                ->maxHeight(config('media.image.max_height')),
        ];
    }

    /**
     * Upload `$file` as the shop's `$field` and delete the file it replaces,
     * so a seller trying a few photos doesn't leave a trail of orphans.
     *
     * @param  'logo'|'banner'  $field
     */
    public function replace(Store $store, UploadedFile $file, string $field): Store
    {
        $disk = config('filesystems.default');
        $previous = $store->{$field};

        $path = $file->store("stores/{$store->id}", [
            'disk' => $disk,
            'visibility' => 'public',
        ]);

        $store->update([$field => Storage::disk($disk)->url($path)]);

        if ($previous && ($oldPath = $this->pathOf($previous, $store))) {
            Storage::disk($disk)->delete($oldPath);
        }

        return $store;
    }

    /**
     * The disk path behind a stored URL — only for files this service put in
     * the shop's own folder, so a URL pointing anywhere else is never deleted.
     */
    private function pathOf(string $url, Store $store): ?string
    {
        $folder = "stores/{$store->id}/";
        $at = strpos($url, $folder);

        return $at === false ? null : substr($url, $at);
    }
}
