<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the pre-checkout per-product delivery concept.
 *
 * `delivery_options` (the "amana, flat 20 MAD" row) and its pivot were an
 * earlier, per-product delivery idea: every workshop offering its own list of
 * carriers at its own prices. It was superseded first by dropping the pivot
 * (`2026_05_20_164923`), then by the checkout's single admin-run delivery
 * priced per destination. Nothing reads `delivery_options` any more — it is
 * replaced wholesale by `delivery_services` + `delivery_rates`, where a
 * carrier lives once and its prices hang off the rate card keyed by
 * destination.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('delivery_options');
    }

    public function down(): void
    {
        Schema::create('delivery_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 20, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
