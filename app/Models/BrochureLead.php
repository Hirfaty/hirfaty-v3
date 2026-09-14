<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One person who asked for the brochure.
 *
 * Write through `BrochureLeadService` rather than here: the address has to be
 * normalised before it reaches the unique index, and a buyer's `seller_type`
 * has to be cleared — doing either at the call site is how bad rows get in.
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $type One of self::TYPES.
 * @property string|null $seller_type One of self::SELLER_TYPES, null for buyers.
 * @property string|null $locale
 * @property int $downloads_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class BrochureLead extends Model
{
    /**
     * Which side of the marketplace they are on.
     */
    public const TYPES = ['buyer', 'seller'];

    /**
     * Asked only of sellers, and kept identical to the `stores.type` enum so a
     * lead that later opens a shop can be lined up against it without a
     * translation table in between.
     */
    public const SELLER_TYPES = ['artist', 'company', 'cooperative'];

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'type',
        'seller_type',
        'locale',
        'downloads_count',
    ];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
