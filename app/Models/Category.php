<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property array<string, string> $name Per-locale names, keyed by locale code.
 * @property-read string $translated_name
 * @property string $slug
 * @property int|null $parent_id
 * @property string|null $image Storage path on the default disk; top-level categories only.
 * @property-read string|null $image_url
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'slug', 'parent_id', 'image'])]
class Category extends Model
{
    /**
     * Header menu cache, one entry per locale. Versioned because the cached
     * shape has changed over time and a stale entry of the old shape crashes
     * the header — bump the version whenever the payload changes.
     */
    public const NAV_CACHE_KEY = 'nav_categories_v3_';

    /**
     * Locales a category name must be available in.
     */
    public const LOCALES = ['fr', 'ar', 'en'];

    /**
     * Locale used when the requested one has no translation yet.
     */
    public const FALLBACK_LOCALE = 'fr';

    /**
     * Always expose the resolved name so any serialised Category (Inertia props,
     * JSON) carries a display string — `name` itself is a per-locale map.
     */
    protected $appends = ['translated_name', 'image_url'];

    /**
     * The header's category menu is cached per locale on the hot path of every
     * response — see HandleInertiaRequests::getNavCategories(). Without this
     * an admin's edit would not show up in the nav for up to an hour.
     */
    protected static function booted(): void
    {
        $forget = function (): void {
            foreach (self::LOCALES as $locale) {
                Cache::forget(self::NAV_CACHE_KEY.$locale);
            }
        };

        static::saved($forget);
        static::deleted($forget);
    }

    protected function casts(): array
    {
        return [
            'name' => 'array',
        ];
    }

    /**
     * Resolve the name for a locale, falling back so the UI never renders blank.
     */
    public function nameFor(?string $locale = null): string
    {
        $names = $this->name ?? [];
        $locale ??= app()->getLocale();

        return $names[$locale]
            ?? $names[self::FALLBACK_LOCALE]
            ?? (string) (collect($names)->filter()->first() ?? '');
    }

    /**
     * Convenience accessor so `$category->translated_name` works in views and
     * is picked up by `append`/`toArray` for Inertia props.
     */
    protected function translatedName(): Attribute
    {
        return Attribute::get(fn (): string => $this->nameFor());
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image
            ? Storage::disk(config('filesystems.default'))->url($this->image)
            : null);
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
