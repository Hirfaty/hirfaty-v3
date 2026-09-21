<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A carrier Hirfaty can ship with — Amana today, DHL etc. later.
 *
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 */
class DeliveryService extends Model
{
    protected $fillable = [
        'slug', 'name', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** One price card per service — see the `delivery_rates` migration. */
    public function rates()
    {
        return $this->hasMany(DeliveryRate::class);
    }
}
