<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Pottery & Ceramics',
            'Leather Goods',
            'Textiles & Weaving',
            'Jewelry & Accessories',
            'Wood & Carpentry',
            'Spices & Food',
            'Painting & Art',
            'Cosmetics & Argan',
        ];

        foreach ($categories as $name) {
            Category::firstOrCreate([
                'name' => $name,
                'slug' => Str::slug($name),
            ]);
        }
    }
}
