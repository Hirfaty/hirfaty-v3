<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of a delivery service's rate card — see the `delivery_rates`
 * migration for the two row shapes.
 *
 * A weight-priced service (DHL) fills `zone` and leaves `fee` null: the price
 * comes from that zone's weight grid, not from the row.
 *
 * @property int $delivery_service_id
 * @property string $country_code
 * @property string $country_name
 * @property int|null $city_id
 * @property int|null $zone
 * @property string|null $fee
 */
class DeliveryRate extends Model
{
    protected $fillable = [
        'delivery_service_id', 'country_code', 'country_name', 'city_id', 'zone', 'fee',
    ];

    protected $casts = [
        'zone' => 'integer',
        'fee' => 'decimal:2',
    ];

    public function service()
    {
        return $this->belongsTo(DeliveryService::class, 'delivery_service_id');
    }

    /** Null on an international rate, which is priced per country. */
    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
