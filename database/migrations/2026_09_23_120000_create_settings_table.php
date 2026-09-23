<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Site-wide settings the admin desk can change without a deploy — the flat
 * delivery fee and whether checkout offers cities and countries, to start.
 *
 * Key/value rather than one column per setting, so the next toggle is a row
 * and not a migration. Values are stored as text and cast by whichever
 * service owns the key (see DeliverySettingsService); a missing row means
 * "use the default", so this table starts empty and needs no seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
