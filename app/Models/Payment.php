<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One trip to the payment page — Payzone's or PayPal's — for one purchase.
 *
 * Payzone wants a fresh `chargeId` every time the buyer is redirected, so a
 * retry after a declined card is a new row against the same purchase; PayPal
 * attempts follow the same rule, one PayPal order per row. The purchase is
 * paid when any one of its rows is — see PaymentService.
 *
 * `amount` / `currency` are what the provider was asked to take: MAD for
 * Payzone, the PayPal currency (with the `exchange_rate` used) for PayPal.
 */
class Payment extends Model
{
    public const PROVIDER_PAYZONE = 'payzone';

    public const PROVIDER_PAYPAL = 'paypal';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'group_reference', 'buyer_id', 'provider', 'charge_id', 'provider_order_id',
        'amount', 'currency', 'exchange_rate', 'status', 'provider_status',
        'provider_transaction_id', 'card_brand', 'paid_at', 'payload',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'paid_at' => 'datetime',
        'payload' => 'array',
    ];

    /** Unguessable, since it also keys the public return URL. */
    public static function newChargeId(): string
    {
        return 'HRFP-'.strtoupper(Str::random(20));
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Every order this payment settles, including the ones still hidden
     * while they wait for it (see Order's settled scope).
     *
     * @return Builder<Order>
     */
    public function orders(): Builder
    {
        return Order::withoutGlobalScope(Order::SETTLED_SCOPE)
            ->where('group_reference', $this->group_reference);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPayPal(): bool
    {
        return $this->provider === self::PROVIDER_PAYPAL;
    }

    /**
     * A MAD figure in this attempt's currency — what refunding part of the
     * purchase means in the money that was actually taken.
     */
    public function inChargedCurrency(float $mad): float
    {
        if ($this->exchange_rate === null) {
            return round($mad, 2);
        }

        return round($mad * (float) $this->exchange_rate, 2);
    }
}
