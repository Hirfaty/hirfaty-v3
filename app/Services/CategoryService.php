<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Admin-side category management. Only top-level categories carry a picture
 * (it is what the homepage tiles show); a subcategory never stores one, and a
 * category given a parent loses the one it had.
 *
 * Parentage is a many-to-many: a subcategory can be filed under several crafts
 * at once, and one with no parents at all is a root.
 */
class CategoryService
{
    /**
     * Every category with the counts the admin table shows and the parent ids
     * its edit dialog preselects. Deliberately flat: a subcategory sits under
     * any number of parents, so the page assembles the tree from `parent_ids`
     * rather than being handed the same row once per parent.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listForAdmin(): Collection
    {
        return Category::withCount(['products', 'stores'])
            ->with('parents')
            ->latest()
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                // Per-locale map; the page renders it as the translation-audit
                // strip, which is why it is sent raw alongside the resolved name.
                'name' => $category->name,
                'translated_name' => $category->translated_name,
                'slug' => $category->slug,
                'image_url' => $category->image_url,
                'products_count' => $category->products_count,
                'stores_count' => $category->stores_count,
                'parent_ids' => $category->parents->pluck('id')->all(),
            ]);
    }

    /**
     * @param  array{name: array<string, string>, parent_ids?: array<int, int|string>|null}  $data
     */
    public function create(array $data, ?UploadedFile $image): Category
    {
        $parentIds = $this->parentIds($data);

        $category = Category::create([
            'name' => $data['name'],
            // The slug is the URL key, so it is derived once from the French
            // name and never changes per locale.
            'slug' => $this->uniqueSlug($data['name'][Category::FALLBACK_LOCALE]),
            'image' => $image && $parentIds === [] ? $this->storeImage($image) : null,
        ]);

        $this->syncParents($category, $parentIds);

        return $category;
    }

    /**
     * The slug is intentionally left untouched — changing it would break
     * existing links to the category.
     *
     * @param  array{name: array<string, string>, parent_ids?: array<int, int|string>|null}  $data
     */
    public function update(Category $category, array $data, ?UploadedFile $image, bool $removeImage): void
    {
        $parentIds = $this->parentIds($data);

        $attributes = ['name' => $data['name']];

        if ($parentIds !== [] || $removeImage) {
            $this->deleteImage($category->image);
            $attributes['image'] = null;
        } elseif ($image) {
            $this->deleteImage($category->image);
            $attributes['image'] = $this->storeImage($image);
        }

        $category->update($attributes);

        $this->syncParents($category, $parentIds);
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

    /**
     * @param  array<int, int>  $parentIds
     */
    private function syncParents(Category $category, array $parentIds): void
    {
        $changes = $category->parents()->sync($parentIds);

        /*
         * Writing to the pivot touches no category row, so none of the model
         * events that normally drop the header's cached menu fire — without
         * this the nav would keep serving the old tree for up to an hour.
         * Only when something actually moved, so a no-op save does not throw
         * the cache away.
         */
        if (array_filter($changes) !== []) {
            Category::forgetNavCache();
        }
    }

    /**
     * Normalises what the form sent: a blank "no parent" option arrives as an
     * empty string, and the same parent can be submitted twice if the page
     * state got out of step.
     *
     * @param  array{parent_ids?: array<int, int|string>|null}  $data
     * @return array<int, int>
     */
    private function parentIds(array $data): array
    {
        return collect($data['parent_ids'] ?? [])
            ->reject(fn ($id) => $id === '' || $id === null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
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
