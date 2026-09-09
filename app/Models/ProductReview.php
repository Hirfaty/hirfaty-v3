<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A buyer's rating of a product they received.
 *
 * Not to be confused with `products.reviewed_by` / `reviewed_at`, which is the
 * admin moderation stamp on a *listing*. This is the customer's opinion of the
 * piece.
 *
 * Writing one directly is not enough to be legitimate — eligibility (a
 * delivered order containing the product) is enforced by `ReviewService`, which
 * is the only path that should create these.
 */
class ProductReview extends Model
{
    protected $fillable = [
        'user_id', 'product_id', 'order_id', 'rating', 'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
