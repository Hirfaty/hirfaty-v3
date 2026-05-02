<?php

namespace Database\Seeders;
use App\Models\City;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = [
            'Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger',
            'Agadir', 'Meknès', 'Oujda', 'Tétouan', 'Salé',
            'Nador', 'Béni Mellal', 'Kénitra', 'El Jadida', 'Laâyoune',
            'Khémisset', 'Settat', 'Safi', 'Mohammedia', 'Dakhla',
        ];

        foreach ($cities as $city) {
            City::firstOrCreate(['name' => $city]);
        }
    }
}
