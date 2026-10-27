<?php

use Database\Seeders\DhlDeliveryRateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Puts DHL Express on a live database and hands it every destination abroad.
 *
 * A deploy only migrates — it never seeds — so this is how the DHL card
 * reaches staging and production. It runs the same seeder a fresh database
 * gets from DatabaseSeeder, then removes Amana's international rows: those
 * were 20 MAD placeholders, and leaving them would offer buyers abroad a
 * carrier that cannot actually ship there at a price nobody charges.
 *
 * Only on a database that already has its delivery card — Amana's row is the
 * sign. A fresh database (and every test run) starts with no carrier at all,
 * gets both cards from DatabaseSeeder instead, and must not have DHL forced on
 * it here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $amanaId = DB::table('delivery_services')->where('slug', 'amana')->value('id');

        if ($amanaId === null) {
            return;
        }

        (new DhlDeliveryRateSeeder)->run();

        DB::table('delivery_rates')
            ->where('delivery_service_id', $amanaId)
            ->whereNull('city_id')
            ->where('country_code', '<>', 'MA')
            ->delete();
    }

    /**
     * Takes DHL's card back off. Amana's placeholder rows are not restored:
     * re-add any country it should serve from the admin delivery page.
     */
    public function down(): void
    {
        DB::table('delivery_services')->where('slug', DhlDeliveryRateSeeder::SLUG)->delete();
    }
};
