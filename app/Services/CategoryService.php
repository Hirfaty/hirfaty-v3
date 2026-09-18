<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Admin-side category management. Only top-level categories carry a picture
 * (it is what the homepage tiles show); a subcategory never stores one, and a
 * category moved under a parent loses the one it had.
 */
class CategoryService
{
    /**
     * @param  array{name: array<string, string>, parent_id?: int|string|null}  $data
     */
    public function create(array $data, ?UploadedFile $image): Category
    {
        $parentId = $data['parent_id'] ?? null;

        return Category::create([
            'name' => $data['name'],
            // The slug is the URL key, so it is derived once from the French
            // name and never changes per locale.
            'slug' => $this->uniqueSlug($data['name'][Category::FALLBACK_LOCALE]),
            'parent_id' => $parentId,
            'image' => $image && ! $parentId ? $this->storeImage($image) : null,
        ]);
    }

    /**
     * The slug is intentionally left untouched — changing it would break
     * existing links to the category.
     *
     * @param  array{name: array<string, string>, parent_id?: int|string|null}  $data
     */
    public function update(Category $category, array $data, ?UploadedFile $image, bool $removeImage): void
    {
        $parentId = $data['parent_id'] ?? null;

        $attributes = [
            'name' => $data['name'],
            'parent_id' => $parentId,
        ];

        if ($parentId || $removeImage) {
            $this->deleteImage($category->image);
            $attributes['image'] = null;
        } elseif ($image) {
            $this->deleteImage($category->image);
            $attributes['image'] = $this->storeImage($image);
        }

        $category->update($attributes);
    }

    /**
     * Refuses (returns false) while products or stores still point at the
     * category, so nothing is silently left without one.
     */
    public function delete(Category $category): bool
    {
        if ($category->products()->exists() || $category->stores()->exists()) {
            return false;
        }

        $this->deleteImage($category->image);
        $category->delete();

        return true;
    }

    private function storeImage(UploadedFile $file): string
    {
        return $file->store('categories', [
            'disk' => config('filesystems.default'),
            'visibility' => 'public',
        ]);
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk(config('filesystems.default'))->delete($path);
        }
    }

    private function uniqueSlug(string $from): string
    {
        $base = Str::slug($from);
        $slug = $base;
        $i = 2;

        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
