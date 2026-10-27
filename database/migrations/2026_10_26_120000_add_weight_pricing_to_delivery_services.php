<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A second way for a carrier to price a basket: by weight and zone, the way
 * DHL Express does, beside the flat fee per destination Amana uses.
 *
 *  - `delivery_services.pricing` says which. A weight-priced service carries
 *    the figures its quote is built from: a fixed fee per shipment, the fuel
 *    surcharge (DHL moves it weekly, so it is edited from the admin rather
 *    than seeded), the weight assumed for a piece with none on file, and the
 *    per-piece surcharges for bulky or heavy pieces.
 *  - `delivery_rates.zone` is where a weight-priced country sits on the
 *    carrier's zone chart. Such a row has no `fee` of its own — the price
 *    comes from the zone's grid and the basket's weight — so `fee` becomes
 *    nullable. The row still decides that the country is served at all.
 *  - `delivery_weight_bands` is the grid itself, per service and zone. A band
 *    with no `step` is a flat price for anything up to `up_to` kg; a band
 *    with a `step` adds `price` per started `step` kg between the band before
 *    it and `up_to`. That is exactly how DHL's card reads: a table to 10 kg,
 *    then "per additional 0.5 kg" and "per additional kg" stretches.
 *
 * Plain strings rather than an enum for `pricing`: enum()->change() breaks
 * on Postgres, and the allowed values are enforced in validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_services', function (Blueprint $table) {
            $table->string('pricing', 20)->default('destination')->after('description');
            $table->decimal('shipment_fee', 10, 2)->default(0)->after('pricing');
            $table->decimal('fuel_surcharge_percent', 5, 2)->default(0)->after('shipment_fee');
            $table->decimal('default_piece_weight', 8, 3)->nullable()->after('fuel_surcharge_percent');
            $table->decimal('handling_fee', 10, 2)->default(0)->after('default_piece_weight');
            $table->decimal('overweight_fee', 10, 2)->default(0)->after('handling_fee');
        });

        Schema::table('delivery_rates', function (Blueprint $table) {
            $table->unsignedTinyInteger('zone')->nullable()->after('city_id');
            $table->decimal('fee', 20, 2)->nullable()->change();
        });

        Schema::create('delivery_weight_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_service_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('zone');
            // Inclusive upper bound, in kg.
            $table->decimal('up_to', 10, 3);
            // Null: a flat price up to `up_to`. Set: `price` per started step.
            $table->decimal('step', 8, 3)->nullable();
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->unique(['delivery_service_id', 'zone', 'up_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_weight_bands');

        // `fee` stays nullable: tightening it back would fail on any zone
        // row still on file, and a nullable fee breaks nothing older.
        Schema::table('delivery_rates', function (Blueprint $table) {
            $table->dropColumn('zone');
        });

        Schema::table('delivery_services', function (Blueprint $table) {
            $table->dropColumn([
                'pricing', 'shipment_fee', 'fuel_surcharge_percent',
                'default_piece_weight', 'handling_fee', 'overweight_fee',
            ]);
        });
    }
};
