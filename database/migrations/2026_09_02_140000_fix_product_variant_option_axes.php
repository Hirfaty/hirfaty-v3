<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Makes `product_variant_options` actually store an axis and a value.
     *
     * The table was designed correctly — `name` is the axis ("Couleur"),
     * `value` the point on it ("Rouge"), and unique(variant, name) means "one
     * value per axis per variant". But the admin wrote the *label* into both
     * (see the old syncVariants: `'value' => $o['value'] ?? $o['name']`), so a
     * red variant was stored as name='Red', value='Red' and the axis was never
     * recorded at all.
     *
     * The storefront derives its selector rows from `name`, so instead of one
     * "Couleur" row offering Rouge and Bleu, it produced two separate rows
     * called "Red" and "Blue" that no variant could satisfy together. This
     * backfills the axis, widens the type vocabulary for craft goods, and
     * drops the colour swatch.
     */
    public function up(): void
    {
        /*
         * `type` is an enum. Changing an enum's members with ->change() is
         * exactly the operation that breaks on Postgres, so the column is
         * rebuilt as a plain string instead: add a temp column, copy across
         * (renaming size -> dimension), drop the enum, rename into place.
         * The allowed values are enforced in ProductVariantOption::TYPES and
         * validated at the controller.
         */
        Schema::table('product_variant_options', function (Blueprint $table) {
            $table->string('type_tmp', 32)->nullable()->after('product_variant_id');
        });

        DB::table('product_variant_options')->update([
            'type_tmp' => DB::raw("CASE WHEN type = 'size' THEN 'dimension' ELSE type END"),
        ]);

        Schema::table('product_variant_options', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('product_variant_options', function (Blueprint $table) {
            $table->renameColumn('type_tmp', 'type');
        });

        /*
         * A variant that somehow carries two options of the same kind would
         * violate unique(variant, name) the moment both are renamed to the
         * same axis. Keep the earliest and drop the rest — this can only
         * happen on data the old UI should never have produced.
         */
        foreach (['color', 'dimension'] as $kind) {
            $duplicates = DB::table('product_variant_options')
                ->select('product_variant_id', DB::raw('MIN(id) as keep_id'))
                ->where('type', $kind)
                ->groupBy('product_variant_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $duplicate) {
                DB::table('product_variant_options')
                    ->where('product_variant_id', $duplicate->product_variant_id)
                    ->where('type', $kind)
                    ->where('id', '!=', $duplicate->keep_id)
                    ->delete();
            }
        }

        /*
         * Set the axis. `value` already holds the label for these rows (it was
         * mirrored from `name`), so only `name` needs correcting.
         *
         * `other` rows are deliberately untouched: that branch of the old UI
         * did capture a real name/value pair, so those rows are already right.
         */
        DB::table('product_variant_options')->where('type', 'color')->update(['name' => 'Couleur']);
        DB::table('product_variant_options')->where('type', 'dimension')->update(['name' => 'Dimension']);

        // Colour is captured as text now, so the swatch has nowhere to show.
        Schema::table('product_variant_options', function (Blueprint $table) {
            $table->dropColumn('color_hex');
        });
    }

    public function down(): void
    {
        Schema::table('product_variant_options', function (Blueprint $table) {
            $table->string('color_hex', 7)->nullable()->after('value');
        });

        // Fold the widened vocabulary back into the original three members.
        DB::table('product_variant_options')
            ->whereIn('type', ['material', 'finish', 'pattern'])
            ->update(['type' => 'other']);

        DB::table('product_variant_options')
            ->where('type', 'dimension')
            ->update(['type' => 'size']);

        Schema::table('product_variant_options', function (Blueprint $table) {
            $table->string('type_tmp', 32)->nullable()->after('product_variant_id');
        });

        DB::table('product_variant_options')->update(['type_tmp' => DB::raw('type')]);

        Schema::table('product_variant_options', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('product_variant_options', function (Blueprint $table) {
            $table->enum('type', ['color', 'size', 'other'])
                ->default('other')
                ->after('product_variant_id');
        });

        DB::table('product_variant_options')->update(['type' => DB::raw('type_tmp')]);

        Schema::table('product_variant_options', function (Blueprint $table) {
            $table->dropColumn('type_tmp');
        });
    }
};
