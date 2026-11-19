<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The country and postcode an order ships to, as fields of their own.
 *
 * Until now an order kept its destination as the one printable line
 * (`shipping_address`), plus `city_id` for a Moroccan city. That is all
 * Cathedis needs, but DHL Express needs the country code and the postcode
 * apart from the street to create a shipment abroad — and the country chosen
 * at checkout was not kept on the order at all.
 *
 * Orders already placed in a Moroccan city are given MA; an older order
 * abroad keeps null, and the desk picks its country when it ships it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->char('shipping_country', 2)->nullable()->after('shipping_phone');
            $table->string('shipping_postal_code', 20)->nullable()->after('shipping_country');
        });

        DB::table('orders')->whereNotNull('city_id')->update(['shipping_country' => 'MA']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_country', 'shipping_postal_code']);
        });
    }
};
