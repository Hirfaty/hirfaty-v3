<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The carriers Hirfaty ships with: Amana today, DHL and others later.
 *
 * This is the header row the `delivery_rates` table hangs off — one price
 * card per service. A service is a business identity (a slug, never an auto
 * id, is what the code refers to), so deactivating a carrier is a flag flip
 * rather than a delete, and the checkout only ever offers `is_active`
 * services.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_services', function (Blueprint $table) {
            $table->id();

            // Stable business identifier — code refers to services by slug
            // ('amana', 'dhl', …), not by the surrogate id.
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();

            $table->boolean('is_active')->default(true);

            // Lower sorts first; the checkout shows services in this order.
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_services');
    }
};
