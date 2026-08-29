<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two things land here:
     *
     *  - `type` classifies the seller (artist, company, cooperative) for
     *    storefront filtering/badging.
     *  - `logo`/`banner` finally get real columns. Both were already declared
     *    in Store::$fillable and in the frontend Store type, but no migration
     *    ever created them — any code path writing to them would have thrown
     *    an "unknown column" SQL error. Nothing currently writes to them, so
     *    this was latent rather than a live bug.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Defaulted rather than left nullable so the seller self-serve
            // setup flow (which does not collect a type) keeps working.
            $table->enum('type', ['artist', 'company', 'cooperative'])
                ->default('artist')
                ->after('city_id');

            $table->string('logo')->nullable()->after('description');
            $table->string('banner')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['type', 'logo', 'banner']);
        });
    }
};
