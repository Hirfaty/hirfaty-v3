<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single option on a variant, e.g. name="Couleur", value="Rouge".
 *
 * @property int $id
 * @property int $product_variant_id
 * @property string $type One of TYPES: color, size or other.
 * @property string $name
 * @property string $value
 * @property string|null $color_hex Swatch, only set when type is `color`.
 */
class ProductVariantOption extends Model
{
    /** Option kinds the admin can pick; drives how the value is captured. */
    public const TYPES = ['color', 'size', 'other'];

    protected $table = 'product_variant_options';

    public $timestamps = false;

    protected $fillable = [
        'product_variant_id',
        'type',
        'name',
        'value',
        'color_hex',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
