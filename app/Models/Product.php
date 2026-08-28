<?php
namespace App\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model implements TranslatableContract
{
    use Translatable;

    public $translatedAttributes = ['name', 'slug', 'description'];

    protected $fillable = [
        'store_id', 'category_id', 'sku',
        'base_price', 'sale_price', 'professional_price', 'professional_sale_price',
        'stock', 'min_order_qty_professional',
        'visibility', 'status', 'has_variants',
        'weight', 'lead_time_days',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    // Price resolution helpers
    public function priceFor(string $customerType): ?float
    {
        return $customerType === 'professional'
            ? ($this->professional_sale_price ?? $this->professional_price)
            : ($this->sale_price ?? $this->base_price);
    }
}