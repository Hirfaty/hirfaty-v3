<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'stock', 'price_override', 'professional_price_override',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributes_(): HasMany
    {
        return $this->hasMany(ProductVariantAttribute::class);
    }

    public function priceFor(string $customerType): float
    {
        if ($customerType === 'professional' && $this->professional_price_override !== null) {
            return $this->professional_price_override;
        }

        if ($customerType !== 'professional' && $this->price_override !== null) {
            return $this->price_override;
        }

        return $this->product->priceFor($customerType);
    }
}