<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two parts of a postal address the book was missing.
 *
 * `address_line` + `city_id` was enough while checkout only had to tell an
 * Amana driver which door to knock on, but a printed label needs the code too,
 * and the redesigned checkout asks for it explicitly.
 *
 * Both are nullable, and deliberately so: there are already saved addresses in
 * the book that predate this, and backfilling a postal code for them would
 * mean inventing one. They stay blank until their owner next edits them.
 *
 * `country` is an ISO-3166 alpha-2 code rather than a name, so it does not
 * need translating and cannot drift between "Maroc", "Morocco" and "المغرب".
 * It defaults to MA and is the only value the UI offers — every city in
 * `cities` is Moroccan and Amana delivers domestically — but it is a column
 * rather than a constant so that shipping abroad later is a data change rather
 * than a schema one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('postal_code', 20)->nullable()->after('city_id');
            $table->string('country', 2)->default('MA')->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['postal_code', 'country']);
        });
    }
};
