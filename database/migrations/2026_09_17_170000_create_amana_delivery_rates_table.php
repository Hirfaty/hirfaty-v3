<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What Amana charges to deliver one basket, by destination.
 *
 * Two shapes of row live in one table:
 *   - Morocco, per city  → country_code = 'MA', city_id set
 *   - Anywhere else      → country_code = 'FR' (etc), city_id null
 *
 * Domestic delivery is priced city by city because that is how Amana's own
 * rate card works; abroad there is one figure per country, so `city_id` is
 * nullable rather than there being a second table for it.
 *
 * Read-only from the admin side (see Admin\DeliveryRateController) — the rates
 * come from Amana, not from us, so they are seeded rather than edited in the
 * app and cannot be changed or deleted by mistake.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amana_delivery_rates', function (Blueprint $table) {
            $table->id();

            // ISO 3166-1 alpha-2, matching `addresses.country`.
            $table->char('country_code', 2);
            // Denormalised on purpose: there is no `countries` table, and the
            // checkout selector needs something to label the option with.
            $table->string('country_name');

            /*
             * Null for an international rate — that country is priced as one
             * destination. Set only for Morocco, where each city has its own
             * figure. `nullOnDelete` rather than cascade: deleting a city
             * should not silently take its rate's history with it, and the
             * resolver falls back to the country-level row anyway.
             */
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('fee', 20, 2);

            $table->timestamps();

            /*
             * One rate per destination. Note Postgres and SQLite both treat
             * NULLs as distinct in a unique index, so this does NOT stop two
             * country-level rows for the same country — DeliveryRateSeeder
             * upserts on (country_code, city_id) to keep that honest.
             */
            $table->unique(['country_code', 'city_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amana_delivery_rates');
    }
};
