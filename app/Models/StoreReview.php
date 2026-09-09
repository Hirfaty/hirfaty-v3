<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A buyer's rating of the shop that fulfilled one delivered order.
 *
 * Scoped to the order, not the shop — see the migration for why. As with
 * `ProductReview`, eligibility is enforced by `ReviewService` rather than here.
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
