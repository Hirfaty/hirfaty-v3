<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One purchasable combination of a product's options (colour, size, ...).
 *
 * @property int $id
 * @property int $product_id
 * @property string|null $sku
 * @property int $stock
 * @property string|null $price_override
 * @property string|null $professional_price_override
 * @property string|null $weight Override of the product's, in its weight unit.
 * @property string|null $length Override of the product's, in its dimension unit.
 * @property string|null $width
 * @property string|null $height
 * @property int $position
 * @property bool $is_active
 */
class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'stock',
        'price_override',
        'professional_price_override',
        'weight',
        'length',
        'width',
        'height',
        'position',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_override' => 'decimal:2',
            'professional_price_override' => 'decimal:2',
            'weight' => 'decimal:3',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Deliberately named `options`, not `attributes` — an `attributes()` relation
     * would collide with Eloquent's internal `$attributes` array.
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProductVariantOption::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('order');
    }

    /**
     * Price for the given audience, falling back to the parent product when this
     * variant does not override it.
     */
    public function priceFor(string $audience = 'individual'): ?float
    {
        $override = $audience === 'professional'
            ? $this->professional_price_override
            : $this->price_override;

        if ($override !== null) {
            return (float) $override;
        }

        return $this->product?->priceFor($audience);
    }

    /**
     * Human label built from the variant's options, e.g. "Rouge / M".
     */
    public function label(): string
    {
        return $this->options
            ->sortBy('name')
            ->pluck('value')
            ->implode(' / ');
    }
}
