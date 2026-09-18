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
        'address_line', 'city_id', 'postal_code', 'country', 'is_default',
    ];

    /** The only country Hirfaty ships to today — see the migration. */
    public const DEFAULT_COUNTRY = 'MA';

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
     *
     * The postal code rides with the city rather than as its own comma-part,
     * because "Fès, 30000" reads as two places while "Fès 30000" reads as one
     * line on a parcel label. `filter()` drops whichever pieces are blank, so
     * an address saved before postal codes existed still produces a clean
     * string with no stray commas.
     */
    public function toShippingLine(): string
    {
        $cityLine = trim(collect([$this->city?->name, $this->postal_code])
            ->filter()
            ->implode(' '));

        return trim(collect([
            $this->recipient_name,
            $this->address_line,
            $cityLine,
        ])->filter()->implode(', '));
    }
}
