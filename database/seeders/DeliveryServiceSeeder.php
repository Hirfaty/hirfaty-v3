<?php

namespace Database\Seeders;

use App\Models\DeliveryService;
use Illuminate\Database\Seeder;

class DeliveryServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DeliveryService::updateOrCreate(
            ['slug' => 'amana'],
            [
                'name' => 'Amana',
                'description' => 'Livraison partout au Maroc via Amana.',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }
}
