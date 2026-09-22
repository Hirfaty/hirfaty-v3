<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A buyer's rating of the shop that fulfilled one delivered order.
 *
 * Scoped to the order, not the shop — see the migration for why.
 *
 * Dormant: nothing writes these any more. Buyers now rate the pieces they
 * received and not the artisan who made them, so `ReviewService` dropped the
 * shop path and the order panel dropped its stars. The table, this model and
 * `stores.average_rating` are all left standing rather than dropped, because
 * the existing rows are real ratings real buyers left — and turning the
 * feature back on should be a UI decision, not a data recovery.
 */
class StoreReview extends Model
{
    protected $fillable = [
        'user_id', 'store_id', 'order_id', 'rating', 'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
