<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The DHL Express shipment carrying one purchase abroad — see the
 * `dhl_shipments` migration.
 *
 * @property int $id
 * @property string $purchase_reference
 * @property string $tracking_number
 * @property list<string>|null $piece_tracking_numbers
 * @property string $product_code
 * @property string $incoterm
 * @property CarbonImmutable $planned_shipping_date
 * @property list<array{weight: float, length: float, width: float, height: float}> $packages
 * @property string $declared_value
 * @property string|null $charge
 * @property string|null $charge_currency
 * @property CarbonImmutable|null $estimated_delivery_date
 * @property string|null $pickup_confirmation_number
 * @property CarbonImmutable|null $pickup_requested_at
 * @property CarbonImmutable|null $pickup_cancelled_at
 * @property string|null $status_code
 * @property string|null $status_description
 * @property list<array{code: string, description: string, at: string|null, location: string|null}>|null $events
 * @property CarbonImmutable|null $synced_at
 * @property CarbonImmutable|null $closed_at
 */
class DhlShipment extends Model
{
    protected $fillable = [
        'purchase_reference', 'tracking_number', 'piece_tracking_numbers',
        'product_code', 'incoterm', 'planned_shipping_date', 'packages',
        'declared_value', 'charge',
        'charge_currency', 'estimated_delivery_date', 'pickup_confirmation_number',
        'pickup_requested_at', 'pickup_cancelled_at', 'status_code',
        'status_description', 'events', 'synced_at', 'closed_at',
    ];

    protected $casts = [
        'piece_tracking_numbers' => 'array',
        'events' => 'array',
        'packages' => 'array',
        'declared_value' => 'decimal:2',
        'charge' => 'decimal:2',
        'planned_shipping_date' => 'date',
        'estimated_delivery_date' => 'date',
        'pickup_requested_at' => 'datetime',
        'pickup_cancelled_at' => 'datetime',
        'synced_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /** The purchase's key: shared by every order one checkout produced. */
    public static function referenceFor(Order $order): string
    {
        return $order->group_reference ?? $order->reference;
    }

    /** @return HasMany<DhlShipmentDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(DhlShipmentDocument::class);
    }

    /** A courier is booked and not cancelled. */
    public function hasPickup(): bool
    {
        return $this->pickup_confirmation_number !== null && $this->pickup_cancelled_at === null;
    }

    /** Every order of the purchase this shipment carries. */
    public function orders()
    {
        return Order::where(fn ($query) => $query
            ->where('group_reference', $this->purchase_reference)
            ->orWhere(fn ($query) => $query
                ->whereNull('group_reference')
                ->where('reference', $this->purchase_reference)));
    }
}
