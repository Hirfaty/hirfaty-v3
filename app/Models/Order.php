<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /**
     * Alphabet for the public reference: Crockford base32, which drops the
     * ambiguous I, L, O and U so a reference can be read aloud or retyped
     * from a screenshot without guesswork.
     */
    private const REFERENCE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const REFERENCE_PREFIX = 'HRF';

    protected $fillable = [
        'reference',
        'buyer_id', 'store_id', 'city_id',
        'tracking_number', 'status',
        'total_amount', 'platform_fee', 'amana_fee', 'seller_earnings',
        'shipping_address', 'notes', 'delivered_at', 'cancellation_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'amana_fee' => 'decimal:2',
        'seller_earnings' => 'decimal:2',
        'delivered_at' => 'datetime',
    ];

    /**
     * Assigning the reference here rather than in CheckoutService means every
     * creation path gets one — the checkout, seeders, factories and tests.
     */
    protected static function booted(): void
    {
        static::creating(function (self $order) {
            $order->reference ??= self::generateReference();
        });
    }

    /**
     * The public-facing identifier. Orders are keyed by an opaque reference in
     * URLs so the sequential primary key — which is global across every store
     * — never reveals the platform's total order volume.
     */
    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * A random, collision-checked reference such as "HRF-2K8F-4M9Q".
     */
    public static function generateReference(): string
    {
        do {
            $body = collect(range(1, 8))
                ->map(fn () => self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)])
                ->join('');

            $reference = self::REFERENCE_PREFIX.'-'.substr($body, 0, 4).'-'.substr($body, 4);
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    // ─── Relationships ───────────────────────────────────────
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function earning()
    {
        return $this->hasOne(Earning::class);
    }

    // ─── Status helpers ──────────────────────────────────────
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isShipped(): bool
    {
        return $this->status === 'shipped';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
