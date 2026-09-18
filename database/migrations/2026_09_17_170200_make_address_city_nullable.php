<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `cities` holds Moroccan cities only, and Hirfaty now ships abroad.
 *
 * A Paris address has no row in that table to point at, so requiring
 * `city_id` made an international address unsaveable — the buyer could check
 * out to one but never keep it in their address book. Nullable instead, with
 * the town itself living in the free-text `address_line` for those.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable(false)->change();
        });
    }
};
