<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Names are stored per locale; the slug is derived once from the French
     * name and stays stable across locales because it is the URL key.
     */
    public function run(): void
    {
        $categories = [
            [
                'fr' => 'Poterie & Céramique',
                'ar' => 'الفخار والخزف',
                'en' => 'Pottery & Ceramics',
            ],
            [
                'fr' => 'Maroquinerie',
                'ar' => 'المصنوعات الجلدية',
                'en' => 'Leather Goods',
            ],
            [
                'fr' => 'Textiles & Tissage',
                'ar' => 'المنسوجات والنسيج',
                'en' => 'Textiles & Weaving',
            ],
            [
                'fr' => 'Bijoux & Accessoires',
                'ar' => 'المجوهرات والإكسسوارات',
                'en' => 'Jewelry & Accessories',
            ],
            [
                'fr' => 'Bois & Menuiserie',
                'ar' => 'الخشب والنجارة',
                'en' => 'Wood & Carpentry',
            ],
            [
                'fr' => 'Épices & Alimentation',
                'ar' => 'التوابل والأغذية',
                'en' => 'Spices & Food',
            ],
            [
                'fr' => 'Peinture & Art',
                'ar' => 'الرسم والفن',
                'en' => 'Painting & Art',
            ],
            [
                'fr' => 'Cosmétiques & Argan',
                'ar' => 'مستحضرات التجميل والأركان',
                'en' => 'Cosmetics & Argan',
            ],
        ];

        foreach ($categories as $names) {
            // Slug derives from the canonical locale, matching CategoryController.
            Category::firstOrCreate(
                ['slug' => Str::slug($names[Category::FALLBACK_LOCALE])],
                ['name' => $names],
            );
        }
    }
}
