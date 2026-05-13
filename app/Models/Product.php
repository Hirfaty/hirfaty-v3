<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'store_id', 'category_id', 'name', 'slug',
        'description', 'base_price', 'sale_price',
        'stock', 'status', 'average_rating',
        'rejection_reason', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'average_rating' => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('order');
    }

    public function images()
    {
        return $this->morphMany(Media::class, 'mediable')
            ->where('type', 'image')
            ->orderBy('order');
    }

    public function videos()
    {
        return $this->morphMany(Media::class, 'mediable')
            ->where('type', 'video');
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function views()
    {
        return $this->hasMany(ProductView::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isPendingReview(): bool
    {
        return $this->status === 'pending_review';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
