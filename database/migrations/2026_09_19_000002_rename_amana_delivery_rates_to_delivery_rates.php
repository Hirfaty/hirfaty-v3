<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generalises the per-destination rate card from Amana-only to one card per
 * delivery service (`delivery_rates`, keyed on `delivery_service_id`).
 *
 * The old table was already shaped for the DHL future — a nullable `city_id`
 * meant "one fee for the whole country" — it just had the carrier's name
 * baked in. So this migration renames the table, moves every row under the
 * Amana service, and replaces the unique key with one that includes the
 * service: `(delivery_service_id, country_code, city_id)`.
 *
 * Note the old unique index keeps its `amana_delivery_rates_`-prefixed name
 * after the rename, so it is dropped by explicit name rather than by letting
 * Laravel recompute one from the new table name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('amana_delivery_rates', 'delivery_rates');

        Schema::table('delivery_rates', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_service_id')->nullable();
        });

        // Everything on the card today is Amana's, so every row moves under
        // that one service before the column tightens to NOT NULL.
        $amanaId = DB::table('delivery_services')->where('slug', 'amana')->value('id');

        DB::table('delivery_rates')->update(['delivery_service_id' => $amanaId]);

        Schema::table('delivery_rates', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_service_id')->nullable(false)->change();

            $table->dropUnique('amana_delivery_rates_country_code_city_id_unique');

            $table->unique(
                ['delivery_service_id', 'country_code', 'city_id'],
                'delivery_rates_delivery_service_id_country_code_city_id_unique',
            );

            // Deleting a carrier is a destroy-the-whole-card operation: its
            // rates are meaningless the moment the service is gone.
            $table->foreign('delivery_service_id', 'delivery_rates_delivery_service_id_foreign')
                ->references('id')
                ->on('delivery_services')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_rates', function (Blueprint $table) {
            $table->dropForeign('delivery_rates_delivery_service_id_foreign');
            $table->dropUnique('delivery_rates_delivery_service_id_country_code_city_id_unique');
            $table->unique(['country_code', 'city_id'], 'amana_delivery_rates_country_code_city_id_unique');
            $table->unsignedBigInteger('delivery_service_id')->nullable()->change();
        });

        Schema::table('delivery_rates', function (Blueprint $table) {
            $table->dropColumn('delivery_service_id');
        });

        Schema::rename('delivery_rates', 'amana_delivery_rates');
    }
};
