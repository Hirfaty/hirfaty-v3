<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One step in an order's history — a status it reached, or (status null) a
 * note from the desk about where the parcel is. Written only through
 * OrderStatusService and Order's `created` hook; read by the buyer's tracking
 * page.
 */
class OrderStatusEvent extends Model
{
    protected $fillable = ['order_id', 'status', 'note'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
