<?php

namespace Database\Seeders;

use App\Models\City;
use App\Services\CityService;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Additive: a city already in the registry only gets the translations it
     * is missing, so re-seeding never overwrites a name the desk typed in.
     */
    public function run(CityService $names): void
    {
        $cities = [
            'Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger',
            'Agadir', 'Meknès', 'Oujda', 'Tétouan', 'Salé',
            'Nador', 'Béni Mellal', 'Kénitra', 'El Jadida', 'Laâyoune',
            'Khémisset', 'Settat', 'Safi', 'Mohammedia', 'Dakhla',
        ];

        foreach ($cities as $name) {
            $city = City::firstOrCreate(['name' => $name]);

            $city->update([
                'name_translations' => ($city->name_translations ?? []) + $names->knownTranslations($name),
            ]);
        }
    }
}
