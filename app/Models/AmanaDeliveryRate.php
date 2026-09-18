<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of Amana's rate card — see the migration for the two row shapes.
 *
 * @property string $country_code
 * @property string $country_name
 * @property int|null $city_id
 * @property string $fee
 */
class AmanaDeliveryRate extends Model
{
    protected $fillable = [
        'country_code', 'country_name', 'city_id', 'fee',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
    ];

    /** Null on an international rate, which is priced per country. */
    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
