<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name The French name — what labels, the desk and the back office read.
 * @property array<string, string>|null $name_translations Arabic and English names, keyed by locale.
 * @property-read string $translated_name
 * @property string|null $region
 * @property int|null $cathedis_city_id
 * @property int|null $cathedis_sector_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|City newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|City newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|City query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|City whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|City whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|City whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|City whereRegion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|City whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class City extends Model
{
    /**
     * The language `name` is written in. Unlike a category, a city keeps a
     * plain string there: the shipping line on an order, the desk's emails and
     * every admin screen read it as is.
     */
    public const SOURCE_LOCALE = 'fr';

    /** The languages `name_translations` holds a name for. */
    public const TRANSLATED_LOCALES = ['ar', 'en'];

    protected $fillable = ['name', 'name_translations', 'region', 'cathedis_city_id', 'cathedis_sector_id'];

    /**
     * Always expose the name in the visitor's language, so any serialised City
     * (Inertia props, JSON) carries one. Selecting `id,name` only still works —
     * it falls back to the French name — so a storefront query that narrows
     * the columns has to keep `name_translations`.
     */
    protected $appends = ['translated_name'];

    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
        ];
    }

    /**
     * The city's name in a language, falling back to the French one when no
     * translation is on file.
     */
    public function nameFor(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === self::SOURCE_LOCALE) {
            return $this->name;
        }

        return ($this->name_translations[$locale] ?? null) ?: $this->name;
    }

    protected function translatedName(): Attribute
    {
        return Attribute::get(fn (): string => $this->nameFor());
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /** The city Cathedis knows this one by — linked by `cathedis:sync-cities`. */
    public function cathedisCity()
    {
        return $this->belongsTo(CathedisCity::class);
    }

    public function cathedisSector()
    {
        return $this->belongsTo(CathedisSector::class);
    }
}
