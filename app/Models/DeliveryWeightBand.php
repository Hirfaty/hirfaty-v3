<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of a weight-priced service's grid, for one zone — see the
 * `delivery_weight_bands` migration for how a band with and without a `step`
 * reads, and WeightPricingService for the arithmetic.
 *
 * @property int $delivery_service_id
 * @property int $zone
 * @property string $up_to
 * @property string|null $step
 * @property string $price
 */
class DeliveryWeightBand extends Model
{
    protected $fillable = [
        'delivery_service_id', 'zone', 'up_to', 'step', 'price',
    ];

    protected $casts = [
        'zone' => 'integer',
        'up_to' => 'decimal:3',
        'step' => 'decimal:3',
        'price' => 'decimal:2',
    ];

    public function service()
    {
        return $this->belongsTo(DeliveryService::class, 'delivery_service_id');
    }
}
