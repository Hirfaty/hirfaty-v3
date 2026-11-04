<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property array<string, string> $name Per-locale names, keyed by locale code.
 * @property-read string $translated_name
 * @property string $slug
 * @property string|null $hs_code Customs tariff code inherited by its products.
 * @property string|null $image Storage path on the default disk.
 * @property-read string|null $image_url
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category roots()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'slug', 'image', 'hs_code'])]
class Category extends Model
{
    /**
     * Header menu cache, one entry per locale. Versioned because the cached
     * shape has changed over time and a stale entry of the old shape crashes
     * the header — bump the version whenever the payload changes.
     */
    public const NAV_CACHE_KEY = 'nav_categories_v4_';

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
        static::saved(fn () => self::forgetNavCache());
        static::deleted(fn () => self::forgetNavCache());
    }

    /**
     * Drop the header menu for every locale.
     *
     * Public because parentage now lives in a pivot, and attaching or
     * detaching one fires no model event — `CategoryService` has to say so
     * itself after syncing, or the nav keeps the old tree for up to an hour.
     */
    public static function forgetNavCache(): void
    {
        foreach (self::LOCALES as $locale) {
            Cache::forget(self::NAV_CACHE_KEY.$locale);
        }
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

    /**
     * The crafts this category sits under. Plural because one subcategory can
     * belong to several at once — "Art de la table" is filed under both
     * Poterie and Dinanderie — which is what the `category_parent` pivot
     * exists for.
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'category_parent', 'category_id', 'parent_id');
    }

    /**
     * The other side of the same pivot. A child listed here may well be listed
     * under another root too, so treat this as "what to show beneath me", not
     * as an exclusive set.
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'category_parent', 'parent_id', 'category_id');
    }

    /**
     * Top-level crafts: the ones no other category claims as a child. This is
     * what `parent_id IS NULL` used to say, and the only place the rule is
     * spelled out now.
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereDoesntHave('parents');
    }
}
