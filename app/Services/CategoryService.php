<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Admin-side category management. Any category can carry a picture, top-level
 * or not; a category keeps its picture when it moves in or out of a parent.
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
     * Rows come in main-category order and `child_ids` in each parent's own
     * order, so the page shows exactly what the storefront will.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listForAdmin(): Collection
    {
        return Category::withCount(['products', 'stores'])
            ->with(['parents', 'children'])
            ->ordered()
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                // Per-locale map; the page renders it as the translation-audit
                // strip, which is why it is sent raw alongside the resolved name.
                'name' => $category->name,
                'translated_name' => $category->translated_name,
                'slug' => $category->slug,
                'hs_code' => $category->hs_code,
                'image_url' => $category->image_url,
                'products_count' => $category->products_count,
                'stores_count' => $category->stores_count,
                'parent_ids' => $category->parents->pluck('id')->all(),
                'child_ids' => $category->children->pluck('id')->all(),
            ]);
    }

    /**
     * Puts one list of siblings in the given order: the main categories when
     * `$parent` is null, otherwise that parent's subcategories. Moving a
     * subcategory under one parent leaves its place under any other alone.
     *
     * Tolerates a stale page: ids that are not (or no longer) siblings are
     * ignored, and siblings the page did not know about keep their relative
     * order after the ones it did.
     *
     * @param  array<int, int|string>  $ids
     */
    public function reorder(?Category $parent, array $ids): void
    {
        $requested = collect($ids)->map(fn ($id) => (int) $id)->unique();
        $siblings = ($parent ? $parent->children() : Category::roots()->ordered())
            ->pluck('categories.id')
            ->map(fn ($id) => (int) $id);

        $ordered = $requested->intersect($siblings)
            ->merge($siblings->diff($requested))
            ->values();

        DB::transaction(function () use ($parent, $ordered) {
            foreach ($ordered as $index => $id) {
                $parent
                    ? $parent->children()->updateExistingPivot($id, ['sort_order' => $index + 1])
                    : Category::whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });

        // Neither write above fires a model event, so the header menu has to
        // be told, or it would keep the old order for up to an hour.
        Category::forgetNavCache();
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
            'image' => $image ? $this->storeImage($image) : null,
            'hs_code' => $this->hsCode($data),
            'sort_order' => $this->nextRootPosition(),
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

        $attributes = ['name' => $data['name'], 'hs_code' => $this->hsCode($data)];

        // The new file wins: the form still sends `remove_image` when the admin
        // removed the old picture and then picked another.
        if ($image) {
            $this->deleteImage($category->image);
            $attributes['image'] = $this->storeImage($image);
        } elseif ($removeImage) {
            $this->deleteImage($category->image);
            $attributes['image'] = null;
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

        $childIds = $category->children()->pluck('categories.id');

        $this->deleteImage($category->image);
        $category->delete();

        // The cascade removed their pivot rows, so any child with no other
        // parent is a main category now, with a position that meant nothing.
        Category::roots()
            ->whereIn('id', $childIds)
            ->orderBy('id')
            ->get()
            ->each(fn (Category $orphan) => $orphan->update(['sort_order' => $this->nextRootPosition()]));

        return true;
    }

    /**
     * A parent it already had keeps its place in that parent's list; a new one
     * takes it at the end. Empty attributes are what tell `sync` to leave an
     * existing row alone.
     *
     * @param  array<int, int>  $parentIds
     */
    private function syncParents(Category $category, array $parentIds): void
    {
        $current = $category->parents()->pluck('categories.id');

        $changes = $category->parents()->sync(
            collect($parentIds)
                ->mapWithKeys(fn (int $id) => [
                    $id => $current->contains($id) ? [] : ['sort_order' => $this->nextChildPosition($id)],
                ])
                ->all(),
        );

        // Losing its last parent makes it a main category, and the position
        // it carried from before means nothing in that list.
        if ($parentIds === [] && $current->isNotEmpty()) {
            $category->update(['sort_order' => $this->nextRootPosition()]);
        }

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

    private function nextRootPosition(): int
    {
        return (int) Category::roots()->max('sort_order') + 1;
    }

    private function nextChildPosition(int $parentId): int
    {
        return (int) DB::table('category_parent')->where('parent_id', $parentId)->max('sort_order') + 1;
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

    /**
     * Digits only, so "6702.90" and "670290" are stored the same way.
     *
     * @param  array<string, mixed>  $data
     */
    private function hsCode(array $data): ?string
    {
        $digits = preg_replace('/\D/', '', (string) ($data['hs_code'] ?? ''));

        return $digits !== '' ? $digits : null;
    }
}
