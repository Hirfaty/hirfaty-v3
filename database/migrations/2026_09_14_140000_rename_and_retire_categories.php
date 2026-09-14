<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Migrations\Migration;

/**
 * Three editorial changes to the taxonomy, applied to environments that are
 * already seeded. `CategorySeeder` is `firstOrCreate`-based and so does nothing
 * to an existing row — renaming or retiring a live category has to happen here.
 *
 * Slugs are deliberately left alone. The slug is the URL key (see
 * `Admin\CategoryController::update`, which never touches it, and the test
 * "updating a category keeps its slug so existing links do not break"), and the
 * homepage tiles in `ExploreByCategories` match on slug fragments. A rename is
 * a change of label, not of identity.
 */
return new class extends Migration
{
    /**
     * Renames, keyed by the slug that identifies the row. Same shape as
     * `CategorySeeder`'s array so the two stay easy to read against each other.
     */
    private const RENAMES = [
        'lanterne-marocaine' => [
            'fr' => 'Luminaires & dinanderie d\'art',
            'ar' => 'الإنارة والنحاسيات الفنية',
            'en' => 'Lighting & Art Metalwork',
        ],
        'textiles-tissage' => [
            'fr' => 'Art de la table & célébrations',
            'ar' => 'فن المائدة والاحتفالات',
            'en' => 'Tableware & Celebrations',
        ],
    ];

    /** The names these rows carried before, so `down()` can put them back. */
    private const PREVIOUS_NAMES = [
        'lanterne-marocaine' => [
            'fr' => 'Lanterne marocaine',
            'ar' => 'الفانوس المغربي',
            'en' => 'Moroccan Lantern',
        ],
        'textiles-tissage' => [
            'fr' => 'Textiles & Tissage',
            'ar' => 'المنسوجات والنسيج',
            'en' => 'Textiles & Weaving',
        ],
    ];

    private const RETIRED_SLUG = 'bijoux-accessoires';

    private const REASSIGN_TO_SLUG = 'maroquinerie';

    public function up(): void
    {
        $this->rename(self::RENAMES);

        $retired = Category::firstWhere('slug', self::RETIRED_SLUG);

        if (! $retired) {
            return;
        }

        /*
         * Anything still pointing at the retired category is moved rather than
         * orphaned. `category_id` is nullable, so deleting outright would leave
         * live products uncategorised — invisible to the category filters and
         * to the homepage tiles, with no record of where they used to sit.
         */
        $destination = Category::firstWhere('slug', self::REASSIGN_TO_SLUG);

        if (! $destination) {
            throw new RuntimeException(
                'Cannot retire "'.self::RETIRED_SLUG.'": the destination category "'
                .self::REASSIGN_TO_SLUG.'" does not exist in this database.'
            );
        }

        Product::where('category_id', $retired->id)->update(['category_id' => $destination->id]);
        Store::where('category_id', $retired->id)->update(['category_id' => $destination->id]);

        $retired->delete();
    }

    /**
     * Puts the labels back and recreates the retired category, but *not* the
     * assignments: once products have been folded into Maroquinerie there is
     * nothing left recording which of them came from Bijoux, so they stay where
     * `up()` put them. Rolling back gives you the old taxonomy, not the old
     * catalogue.
     */
    public function down(): void
    {
        $this->rename(self::PREVIOUS_NAMES);

        Category::firstOrCreate(
            ['slug' => self::RETIRED_SLUG],
            ['name' => [
                'fr' => 'Bijoux & Accessoires',
                'ar' => 'المجوهرات والإكسسوارات',
                'en' => 'Jewelry & Accessories',
            ]],
        );
    }

    /**
     * Saved one model at a time rather than as a bulk `update()`: the header's
     * category menu is cached per locale for an hour, and only `Category`'s
     * `saved` hook clears it (see `Category::booted`). A query-builder update
     * would leave the old names in the nav of every page until that expired.
     *
     * @param  array<string, array<string, string>>  $names  Keyed by slug.
     */
    private function rename(array $names): void
    {
        foreach ($names as $slug => $name) {
            Category::firstWhere('slug', $slug)?->update(['name' => $name]);
        }
    }
};
