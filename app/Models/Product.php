<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** Audience whose price list applies to a normal (retail) buyer. */
    public const AUDIENCE_INDIVIDUAL = 'individual';

    /** Audience whose price list applies to a bulk/professional buyer. */
    public const AUDIENCE_PROFESSIONAL = 'professional';

    protected $fillable = [
        'store_id', 'category_id', 'name', 'slug', 'sku',
        'description', 'base_price', 'sale_price',
        'professional_price', 'professional_sale_price',
        'stock', 'min_order_qty_professional',
        'visibility', 'has_variants',
        'status', 'average_rating',
        'rejection_reason', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'professional_price' => 'decimal:2',
        'professional_sale_price' => 'decimal:2',
        'average_rating' => 'decimal:2',
        'has_variants' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('order');
    }

    public function images()
    {
        return $this->morphMany(Media::class, 'mediable')
            ->where('type', 'image')
            ->orderBy('order');
    }

    public function videos()
    {
        return $this->morphMany(Media::class, 'mediable')
            ->where('type', 'video');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Map a user to the price list that applies to them. Guests and normal
     * buyers get retail pricing; only *verified* professional buyers get the
     * pro list — anyone can self-register as a trade account, so the rate is
     * gated on an admin having reviewed it.
     *
     * This is the single choke point for that decision: product visibility,
     * the storefront resource and CheckoutService all route through it, so
     * what a trade buyer is quoted and what they are billed cannot drift
     * apart.
     */
    public static function audienceFor(?User $user): string
    {
        return $user?->isVerifiedProfessionalBuyer()
            ? self::AUDIENCE_PROFESSIONAL
            : self::AUDIENCE_INDIVIDUAL;
    }

    /**
     * Everything the storefront is allowed to show and sell.
     *
     * `published` alone is no longer enough: an admin can publish a product
     * before its price has been agreed (base_price NULL), and an unpriced
     * product has no figure to put on a card, sort by, or bill. It stays in
     * the catalogue and in the admin lists — it just isn't on sale yet.
     *
     * Every public query goes through here rather than repeating the pair of
     * conditions, so "priced" can never be remembered in one listing and
     * forgotten in another.
     */
    public function scopeStorefront($query)
    {
        return $query->where('status', 'published')->whereNotNull('base_price');
    }

    /**
     * Priced, and therefore quotable and billable. The row-level counterpart
     * of scopeStorefront() — used on a product already in hand (a cart line,
     * a checkout item) where re-querying would be wasteful.
     */
    public function isPriced(): bool
    {
        return $this->base_price !== null;
    }

    /**
     * Effective unit price for an audience, preferring an active sale price.
     *
     * Professional pricing is optional — a product sold only at retail falls
     * back to the normal price rather than returning null and breaking totals.
     */
    public function priceFor(string $audience = self::AUDIENCE_INDIVIDUAL): ?float
    {
        if ($audience === self::AUDIENCE_PROFESSIONAL) {
            $pro = $this->professional_sale_price ?? $this->professional_price;

            if ($pro !== null) {
                return (float) $pro;
            }
        }

        $retail = $this->sale_price ?? $this->base_price;

        return $retail === null ? null : (float) $retail;
    }

    /**
     * Minimum quantity this audience must order in one go.
     */
    public function minOrderQtyFor(string $audience = self::AUDIENCE_INDIVIDUAL): int
    {
        return $audience === self::AUDIENCE_PROFESSIONAL
            ? max(1, (int) $this->min_order_qty_professional)
            : 1;
    }

    /**
     * Whether this product may be shown to the given audience at all.
     */
    public function isVisibleTo(string $audience = self::AUDIENCE_INDIVIDUAL): bool
    {
        return $this->visibility === 'both' || $this->visibility === $audience;
    }

    public function views()
    {
        return $this->hasMany(ProductView::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Parked by the admin with incomplete details; never shown in the storefront.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPendingReview(): bool
    {
        return $this->status === 'pending_review';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // public function deliveryOptions()
    // {
    //     return $this->belongsToMany(DeliveryOption::class, 'product_delivery_option')
    //             ->withPivot('custom_price');
    // }
}
