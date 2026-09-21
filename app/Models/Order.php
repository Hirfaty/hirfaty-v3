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
        // Shared by every order one checkout produced — see the migration.
        'group_reference',
        'buyer_id', 'store_id', 'city_id',
        'tracking_number', 'status',
        'total_amount', 'platform_fee', 'delivery_fee', 'seller_earnings',
        // What the buyer was reading at checkout, and the rate that was live
        // then. Presentation history, not money: the order settles in MAD.
        'display_currency', 'display_rate',
        // Which carrier shipped and what it was called then — a snapshot, like
        // shipping_address, so a carrier renamed later can't rewrite the
        // history of an order it already carried. `delivery_service_id` alone
        // would go null if that service were deleted; the name is what emails
        // and dispatch screens actually read.
        'delivery_service_id', 'delivery_service_name',
        'shipping_address', 'shipping_phone', 'notes', 'delivered_at', 'cancellation_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'seller_earnings' => 'decimal:2',
        'display_rate' => 'decimal:6',
        'delivered_at' => 'datetime',
    ];

    /**
     * What this order's total looked like to the buyer, or null when they
     * were reading MAD (or the order predates currency tracking).
     *
     * Uses the rate stored at checkout, never a live one — re-converting an
     * old order at today's rate would misreport what was actually quoted.
     */
    public function displayTotal(): ?float
    {
        if ($this->display_currency === null || $this->display_rate === null) {
            return null;
        }

        $decimals = (int) (config("currency.supported.{$this->display_currency}.decimals") ?? 2);

        return round((float) $this->total_amount * (float) $this->display_rate, $decimals);
    }

    /**
     * What Hirfaty takes and what the artisan is left with never leaves the
     * server.
     *
     * Both columns are still written on every checkout (CheckoutService) and
     * are readable in PHP as normal — this only stops them being serialised
     * into an Inertia prop or a JSON response. Orders are handed to the
     * frontend as whole models in several places (seller + admin order
     * screens), so dropping the figures from the pages alone would have left
     * them a devtools panel away from any seller who looked. Delete these two
     * entries to put the numbers back on the wire.
     */
    protected $hidden = [
        'platform_fee',
        'seller_earnings',
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

    /**
     * Every order from the same checkout, this one included.
     *
     * Not a relationship: `group_reference` is nullable for orders placed
     * before it existed, and a `hasMany` on a null key would match every
     * other ungrouped order in the table. Falls back to a collection of one.
     */
    public function groupOrders()
    {
        if (! $this->group_reference) {
            return self::where('id', $this->id);
        }

        return self::where('group_reference', $this->group_reference);
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

    /** The carrier, nulled if it was ever deleted — the name snapshot stays. */
    public function deliveryService()
    {
        return $this->belongsTo(DeliveryService::class, 'delivery_service_id');
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
