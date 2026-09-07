<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A saved delivery address in a buyer's address book.
 *
 * Not what an order ships to — Order keeps its own `shipping_address` string,
 * snapshotted at checkout, so past orders stay accurate after this row is
 * edited or deleted.
 */
class Address extends Model
{
    protected $fillable = [
        'user_id', 'label', 'recipient_name', 'recipient_phone',
        'address_line', 'city_id', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /**
     * The address as checkout would use it — one string, the way
     * `orders.shipping_address` stores it.
     */
    public function toShippingLine(): string
    {
        return trim(collect([
            $this->recipient_name,
            $this->address_line,
            $this->city?->name,
        ])->filter()->implode(', '));
    }
}
