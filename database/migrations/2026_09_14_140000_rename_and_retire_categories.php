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

        $products = Product::where('category_id', $retired->id);
        $stores = Store::where('category_id', $retired->id);

        /*
         * The destination is only needed if something actually points at the
         * retired category — and it is only *looked up* in that case, because
         * the taxonomies differ between environments. Staging, for one, has no
         * `maroquinerie` row at all (an admin deleted it) while its
         * `bijoux-accessoires` is empty: demanding a destination there failed a
         * deploy over a category that had nothing to reassign.
         *
         * When there is something to move, though, this still refuses to guess.
         * `category_id` is nullable, so carrying on without a destination would
         * silently orphan live products — invisible to the category filters and
         * to the homepage tiles, with no record of where they used to sit.
         */
        $orphanCount = $products->count() + $stores->count();

        if ($orphanCount > 0) {
            $destination = Category::firstWhere('slug', self::REASSIGN_TO_SLUG);

            if (! $destination) {
                throw new RuntimeException(sprintf(
                    'Cannot retire "%s": it still has %d product(s)/store(s) to move, '
                    .'but the destination category "%s" does not exist here. '
                    .'Existing slugs: %s. Either create that category or reassign '
                    .'those rows by hand in /admin/categories first.',
                    self::RETIRED_SLUG,
                    $orphanCount,
                    self::REASSIGN_TO_SLUG,
                    Category::orderBy('id')->pluck('slug')->implode(', '),
                ));
            }

            $products->update(['category_id' => $destination->id]);
            $stores->update(['category_id' => $destination->id]);
        }

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
