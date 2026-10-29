<?php

use Database\Seeders\CathedisDeliveryRateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Puts Cathedis on a live database and hands it Morocco.
 *
 * A deploy only migrates — it never seeds — so this is how the Cathedis card
 * reaches staging and production. It runs the same seeder a fresh database
 * gets from DatabaseSeeder (which also adds every Cathedis locality missing
 * from the city registry), then deactivates Amana. Amana is switched off, not
 * deleted: past orders point at its row, and its card stays on file in case
 * it is ever turned back on.
 *
 * Only on a database that already has its delivery card — Amana's row is the
 * sign. A fresh database (and every test run) starts with no carrier at all
 * and gets Cathedis from DatabaseSeeder instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('delivery_services')->where('slug', 'amana')->exists()) {
            return;
        }

        (new CathedisDeliveryRateSeeder)->run();

        DB::table('delivery_services')->where('slug', 'amana')->update(['is_active' => false]);
    }

    /**
     * Turns Amana back on and takes Cathedis's card off. The cities the
     * seeder added stay in the registry: addresses and orders may point at
     * them by now.
     */
    public function down(): void
    {
        DB::table('delivery_services')->where('slug', 'amana')->update(['is_active' => true]);
        DB::table('delivery_services')->where('slug', CathedisDeliveryRateSeeder::SLUG)->delete();
    }
};
