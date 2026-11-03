<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cathedis's own list of the cities and sectors it delivers to, as its public
 * endpoint (/ws/public/c2c/city) gives them, and the link from each of our
 * cities to the one Cathedis knows it by.
 *
 * Cathedis's ids are kept as the primary keys: a delivery is created with
 * Cathedis's exact city and sector, so ours would only be a second name for
 * the same row. Filled and refreshed by `cathedis:sync-cities`
 * (CathedisCitySyncService) — never by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cathedis_cities', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            // BIG_CITIES, SMALL_CITIES or SOUTH_CITIES.
            $table->string('zone')->nullable();
            // The other spellings Cathedis resolves to this city ("Casa", "FÈS"…).
            $table->json('aliases')->nullable();
            // Cathedis's deliveryAvailability: false while it does not deliver there.
            $table->boolean('delivers')->default(true);
            // Archived by Cathedis, or gone from its list since the last sync.
            $table->boolean('archived')->default(false);
            $table->timestamps();
        });

        Schema::create('cathedis_sectors', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('cathedis_city_id')->constrained('cathedis_cities')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->foreignId('cathedis_city_id')->nullable()->constrained('cathedis_cities')->nullOnDelete();
            // Only for a locality Cathedis files as a sector of a larger city
            // (Mimosa → Mohammadia); null sends the city's "Autre".
            $table->foreignId('cathedis_sector_id')->nullable()->constrained('cathedis_sectors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cathedis_sector_id');
            $table->dropConstrainedForeignId('cathedis_city_id');
        });

        Schema::dropIfExists('cathedis_sectors');
        Schema::dropIfExists('cathedis_cities');
    }
};
