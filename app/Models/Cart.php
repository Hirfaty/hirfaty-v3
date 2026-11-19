<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    // `guest_token_hash`: a visitor's basket, before they sign in — see
    // CartService::current().
    protected $fillable = ['user_id', 'guest_token_hash'];

    protected $hidden = ['guest_token_hash'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}
