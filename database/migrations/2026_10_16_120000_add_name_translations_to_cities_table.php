<?php

use App\Services\CityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cities get their Arabic and English names. `name` stays the French one —
 * see City::SOURCE_LOCALE — and the registry already in the database is
 * filled from CityService's list. A city that list does not know keeps
 * showing its French name in every language until it is given one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->json('name_translations')->nullable();
        });

        $names = app(CityService::class);

        DB::table('cities')
            ->orderBy('id')
            ->select(['id', 'name'])
            ->each(function (object $city) use ($names) {
                $translations = $names->knownTranslations($city->name);

                if ($translations === []) {
                    return;
                }

                DB::table('cities')->where('id', $city->id)->update([
                    'name_translations' => json_encode($translations, JSON_UNESCAPED_UNICODE),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('name_translations');
        });
    }
};
