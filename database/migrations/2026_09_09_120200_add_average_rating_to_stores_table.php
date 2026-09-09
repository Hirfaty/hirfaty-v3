<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised shop rating, mirroring `products.average_rating`.
 *
 * Stored rather than aggregated on read because a shop's average is wanted
 * alongside a *list* of shops or products — computing it inline would be a
 * correlated subquery per row on exactly the queries that are already the
 * heaviest on the site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('average_rating', 3, 2)->default(0.00)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('average_rating');
        });
    }
};
