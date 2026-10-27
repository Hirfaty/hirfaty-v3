<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A carrier Hirfaty can ship with — Amana in Morocco, DHL Express abroad.
 *
 * Priced one of two ways (`pricing`): a flat fee per destination, read
 * straight off the `delivery_rates` row (Amana), or by the basket's weight on
 * a zone grid (DHL — see WeightPricingService). The money columns below only
 * mean something for a weight-priced service.
 *
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string $pricing
 * @property string $shipment_fee Added once to every weight-priced quote, VAT included.
 * @property string $fuel_surcharge_percent
 * @property string|null $default_piece_weight kg assumed for a piece with no weight on file.
 * @property string $handling_fee Per bulky or heavy piece.
 * @property string $overweight_fee Per piece over 70 kg, instead of the handling fee.
 * @property bool $is_active
 * @property int $sort_order
 */
class DeliveryService extends Model
{
    /** A fee per Moroccan city or per foreign country. */
    public const PRICING_DESTINATION = 'destination';

    /** A zone per country, priced on a weight grid. */
    public const PRICING_WEIGHT = 'weight';

    protected $fillable = [
        'slug', 'name', 'description', 'pricing', 'shipment_fee',
        'fuel_surcharge_percent', 'default_piece_weight', 'handling_fee',
        'overweight_fee', 'is_active', 'sort_order',
    ];

    protected $attributes = [
        'pricing' => self::PRICING_DESTINATION,
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'shipment_fee' => 'decimal:2',
        'fuel_surcharge_percent' => 'decimal:2',
        'default_piece_weight' => 'decimal:3',
        'handling_fee' => 'decimal:2',
        'overweight_fee' => 'decimal:2',
    ];

    /** One price card per service — see the `delivery_rates` migration. */
    public function rates()
    {
        return $this->hasMany(DeliveryRate::class);
    }

    /** The zone × weight grid of a weight-priced service. */
    public function weightBands()
    {
        return $this->hasMany(DeliveryWeightBand::class);
    }

    public function isWeightPriced(): bool
    {
        return $this->pricing === self::PRICING_WEIGHT;
    }
}
