<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which price list a purchase was billed on: `individual` (Particulier) or
 * `professional` (Entreprise) — the buyer's choice at checkout
 * (PriceModeService), written by CheckoutService on every order of the
 * basket.
 *
 * A plain string rather than an enum (enum()->change() does not survive
 * Postgres), and nullable: orders placed before this existed are not
 * guessed at — they read as "unknown".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('price_mode', 20)->nullable()->after('display_rate')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['price_mode']);
            $table->dropColumn('price_mode');
        });
    }
};
