<?php

namespace Database\Seeders;

use App\Models\DeliveryOption;
use Illuminate\Database\Seeder;

class DeliveryOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DeliveryOption::firstOrCreate(
            ['slug' => 'amana'],
            [
                'name' => 'Amana',
                'description' => 'Livraison partout au Maroc via Amana.',
                'price' => 20.00,
                'is_active' => true,
            ]
        );
    }
}
