<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOption extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'price', 'is_active'];

    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_delivery_option')
            ->withPivot('custom_price');
    }
}
