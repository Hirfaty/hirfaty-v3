<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $category_id
 * @property int|null $city_id
 * @property string $type One of TYPES: artist, company or cooperative.
 * @property string $store_name
 * @property string $slug
 * @property string|null $description
 * @property string|null $logo Public URL of the shop's square photo.
 * @property string|null $banner Public URL of the shop's cover image.
 * @property string $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Category|null $category
 * @property-read City|null $city
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereCityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereStoreName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Store extends Model
{
    /** How the seller behind the shop is legally organised. */
    public const TYPES = ['artist', 'company', 'cooperative'];

    /** Filled in but never looked at by an admin. Cannot sell yet. */
    public const VERIFICATION_PENDING = 'pending';

    /** Reviewed and cleared. The only state that may publish products. */
    public const VERIFICATION_APPROVED = 'approved';

    /** Reviewed and turned down; `rejection_reason` says why. */
    public const VERIFICATION_REJECTED = 'rejected';

    public const VERIFICATION_STATUSES = [
        self::VERIFICATION_PENDING,
        self::VERIFICATION_APPROVED,
        self::VERIFICATION_REJECTED,
    ];

    /**
     * What a shop must have filled in before it is worth an admin's time.
     *
     * Deliberately the same list the seller is nagged about in the status bar
     * and the same list Seller\SettingsController requires — one definition, so
     * "complete" cannot mean different things in the two places.
     */
    public const REQUIRED_PROFILE_FIELDS = [
        'store_name', 'category_id', 'city_id', 'description',
    ];

    protected $fillable = [
        'user_id', 'category_id', 'city_id', 'type', 'store_name', 'slug',
        'description', 'logo', 'banner', 'status', 'verification_status',
        'submitted_at', 'approved_at', 'approved_by', 'rejection_reason',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'average_rating' => 'decimal:2',
    ];

    /**
     * Ratings left by buyers for delivered orders from this shop.
     *
     * `average_rating` is deliberately absent from `$fillable`: it is derived
     * from these rows and is only ever written by `ReviewService`, so allowing
     * a mass assignment to set it would let the cached figure disagree with
     * the reviews it summarises.
     */
    public function reviews()
    {
        return $this->hasMany(StoreReview::class);
    }

    // ── Verification ─────────────────────────────────────────────────

    public function isPendingVerification(): bool
    {
        return $this->verification_status === self::VERIFICATION_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->verification_status === self::VERIFICATION_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->verification_status === self::VERIFICATION_REJECTED;
    }

    /**
     * Every field in REQUIRED_PROFILE_FIELDS is filled in.
     *
     * `blank()` rather than `=== null` so a description saved as whitespace
     * counts as missing — otherwise a space bar gets a shop into the review
     * queue with nothing to review.
     *
     * @return array<int, string> The missing field names, empty when complete.
     */
    public function missingProfileFields(): array
    {
        return array_values(array_filter(
            self::REQUIRED_PROFILE_FIELDS,
            fn (string $field) => blank($this->{$field}),
        ));
    }

    public function hasCompleteProfile(): bool
    {
        return $this->missingProfileFields() === [];
    }

    /**
     * Whether this shop may put products in front of buyers.
     *
     * Both halves matter and they are checked together on purpose: a complete
     * profile that no admin has cleared is not sellable, and an approved shop
     * whose details were later emptied out is not either.
     */
    public function canSell(): bool
    {
        return $this->isApproved() && $this->hasCompleteProfile();
    }

    public function isArtist(): bool
    {
        return $this->type === 'artist';
    }

    public function isCompany(): bool
    {
        return $this->type === 'company';
    }

    public function isCooperative(): bool
    {
        return $this->type === 'cooperative';
    }

    // Relationships
    public function user()
    {
        return $this->belongsto(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // public function reviews()
    // {
    //     return $this->hasMany(Review::class);
    // }

    public function earnings()
    {
        return $this->hasMany(Earning::class);
    }
}
