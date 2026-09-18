<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Amana parcel can carry several orders, so its number is not unique.
 *
 * `tracking_number` was unique back when each seller shipped their own piece
 * and every order therefore had its own consignment. Hirfaty now collects a
 * whole basket and sends it as one parcel, so the same number is written to
 * every order in that purchase — which the unique index rejected outright,
 * failing the second order and leaving the group half-updated.
 *
 * Kept indexed, because the admin desk searches on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['tracking_number']);
            $table->index('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['tracking_number']);
            $table->unique('tracking_number');
        });
    }
};
