<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'buyer_id', 'store_id', 'city_id',
        'tracking_number', 'status',
        'total_amount', 'platform_fee', 'amana_fee', 'seller_earnings',
        'shipping_address', 'notes', 'delivered_at',
    ];

    protected $casts = [
        'total_amount'    => 'decimal:2',
        'platform_fee'    => 'decimal:2',
        'amana_fee'       => 'decimal:2',
        'seller_earnings' => 'decimal:2',
        'delivered_at'    => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────────────
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function earning()
    {
        return $this->hasOne(Earning::class);
    }

    // ─── Status helpers ──────────────────────────────────────
    public function isPending():   bool { return $this->status === 'pending'; }
    public function isConfirmed(): bool { return $this->status === 'confirmed'; }
    public function isShipped():   bool { return $this->status === 'shipped'; }
    public function isDelivered(): bool { return $this->status === 'delivered'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
}
