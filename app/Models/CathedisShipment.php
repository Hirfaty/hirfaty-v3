<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The parcel Cathedis carries for one purchase — see the
 * `cathedis_shipments` migration.
 *
 * @property int $id
 * @property string $purchase_reference
 * @property int $delivery_id
 * @property string $code
 * @property string $amount
 * @property string $payment_type
 * @property string $range_weight
 * @property string|null $delivery_status
 * @property string|null $delivery_status_type
 * @property string|null $payment_status
 * @property CarbonImmutable|null $pickup_requested_at
 * @property CarbonImmutable|null $synced_at
 * @property CarbonImmutable|null $closed_at
 */
class CathedisShipment extends Model
{
    protected $fillable = [
        'purchase_reference', 'delivery_id', 'code', 'amount', 'payment_type',
        'range_weight', 'delivery_status', 'delivery_status_type',
        'payment_status', 'pickup_requested_at', 'synced_at', 'closed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'pickup_requested_at' => 'datetime',
        'synced_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /** The purchase's key: shared by every order one checkout produced. */
    public static function referenceFor(Order $order): string
    {
        return $order->group_reference ?? $order->reference;
    }

    /** Every order of the purchase this parcel carries. */
    public function orders()
    {
        return Order::where(fn ($query) => $query
            ->where('group_reference', $this->purchase_reference)
            ->orWhere(fn ($query) => $query
                ->whereNull('group_reference')
                ->where('reference', $this->purchase_reference)));
    }
}
