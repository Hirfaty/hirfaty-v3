<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The town of an address abroad, as a field of its own.
 *
 * `cities` holds Moroccan cities only, so an address abroad had nowhere to
 * keep its town but the free-text line — and DHL Express needs it apart to
 * ship. Checkout now asks for it beside the country:
 *  - `addresses.city_name`: the town of a saved address abroad (null when
 *    `city_id` names a Moroccan city);
 *  - `orders.shipping_city`: the town an order ships to, Moroccan or not,
 *    snapshotted like the rest of the destination.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('city_name', 100)->nullable()->after('city_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_city', 100)->nullable()->after('shipping_phone');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('city_name');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_city');
        });
    }
};
