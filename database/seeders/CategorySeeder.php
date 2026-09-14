<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Names are stored per locale; the slug is derived once from the French
     * name and stays stable across locales because it is the URL key.
     *
     * Purely additive: `firstOrCreate` means dropping an entry from the array
     * below stops *new* environments creating it, but does nothing to a
     * database that already has the row. Retiring a category from a live
     * environment is therefore a deliberate deletion, not a re-seed — and it
     * nulls `category_id` on every product and store pointing at it, so it is
     * not something this seeder should ever do implicitly.
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
            /*
             * Slugs stay `textiles-tissage` and `lanterne-marocaine` below,
             * because `Str::slug` is applied to the *French* name — so renaming
             * these two here would hand a fresh database different slugs from
             * every existing one, and the homepage tiles in
             * `ExploreByCategories` match on those fragments. The rename for
             * already-seeded databases lives in
             * `2026_09_14_140000_rename_and_retire_categories`, which keeps the
             * slugs; these entries are pinned to match it.
             */
            [
                'slug' => 'textiles-tissage',
                'fr' => 'Art de la table & célébrations',
                'ar' => 'فن المائدة والاحتفالات',
                'en' => 'Tableware & Celebrations',
            ],
            [
                'fr' => 'Bois & Menuiserie',
                'ar' => 'الخشب والنجارة',
                'en' => 'Wood & Carpentry',
            ],
            [
                'fr' => 'Peinture & Art',
                'ar' => 'الرسم والفن',
                'en' => 'Painting & Art',
            ],
            [
                'slug' => 'lanterne-marocaine',
                'fr' => 'Luminaires & dinanderie d\'art',
                'ar' => 'الإنارة والنحاسيات الفنية',
                'en' => 'Lighting & Art Metalwork',
            ],
        ];

        foreach ($categories as $entry) {
            // Slug derives from the canonical locale, matching CategoryController,
            // unless the entry pins one because its label has since been renamed.
            $slug = $entry['slug'] ?? Str::slug($entry[Category::FALLBACK_LOCALE]);
            $names = Arr::only($entry, Category::LOCALES);

            Category::firstOrCreate(
                ['slug' => $slug],
                ['name' => $names],
            );
        }
    }
}
