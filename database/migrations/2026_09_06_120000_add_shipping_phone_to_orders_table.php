<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The number Amana calls to deliver the order.
 *
 * Checkout never collected one: `orders` carried a free-text
 * `shipping_address` and nothing else, so a courier holding a parcel had no
 * way to reach the buyer. `addresses.recipient_phone` existed but was only
 * ever the buyer's own address book — checkout didn't read it, and the order
 * had nowhere to put it.
 *
 * Nullable, so the orders already in the table stay valid. New orders always
 * carry one: CheckoutController requires it on the way in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_phone', 30)->nullable()->after('shipping_address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_phone');
        });
    }
};
