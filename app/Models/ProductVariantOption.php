<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One axis/value pair on a variant, e.g. name="Couleur", value="Rouge".
 *
 * `name` is the axis and `value` the point on it — they are not two names for
 * the same label. The storefront groups variants by `name` to build its
 * selector rows, so writing the label into both (as the admin used to) yields
 * one dead selector row per value instead of one row per axis.
 * unique(product_variant_id, name) enforces one value per axis per variant.
 *
 * @property int $id
 * @property int $product_variant_id
 * @property string $type One of TYPES.
 * @property string $name The axis, e.g. "Couleur".
 * @property string $value The chosen point, e.g. "Rouge".
 */
class ProductVariantOption extends Model
{
    /**
     * Axis kinds, tuned for craft goods. Every kind but `other` carries a
     * canonical label (see AXIS_LABELS) so axis naming stays consistent across
     * products — and therefore filterable later. `other` lets the seller name
     * the axis themselves.
     */
    public const TYPES = ['color', 'dimension', 'material', 'finish', 'pattern', 'other'];

    /** Canonical axis name per type. `other` is named by the seller. */
    public const AXIS_LABELS = [
        'color' => 'Couleur',
        'dimension' => 'Dimension',
        'material' => 'Matière',
        'finish' => 'Finition',
        'pattern' => 'Motif',
    ];

    protected $table = 'product_variant_options';

    public $timestamps = false;

    protected $fillable = [
        'product_variant_id',
        'type',
        'name',
        'value',
    ];

    /**
     * The axis name a type must use, or null for `other`, which the seller
     * names. One source of truth, so the canonical labels can't drift between
     * the admin form and the server.
     */
    public static function axisFor(string $type): ?string
    {
        return self::AXIS_LABELS[$type] ?? null;
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
