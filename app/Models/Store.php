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

    protected $fillable = [
        'user_id', 'category_id', 'city_id', 'type', 'store_name', 'slug',
        'description', 'logo', 'banner', 'status',
        'submitted_at', 'approved_at', 'approved_by', 'rejection_reason',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

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
