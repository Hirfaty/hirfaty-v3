<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a carrier (DHL first) needs to quote, label and clear a parcel.
 *
 *  - Weight and packed dimensions live on the product, with optional
 *    per-variant overrides: a 2 m rug and a 3 m rug of the same design ship
 *    in very different boxes. Figures are stored as the admin typed them,
 *    next to the unit they chose — see ProductShippingService for the kg/cm
 *    conversions a carrier call should use.
 *  - `origin_country` is Morocco for everything today (Hirfaty ships from its
 *    own warehouse), so it is a column with a default rather than a constant:
 *    once sellers ship from their own countries it becomes editable without
 *    another migration.
 *  - The HS (tariff) code is set once per category; a product only carries
 *    one when it needs to differ from its category's.
 *
 * Plain strings rather than enums for the unit columns: enum()->change()
 * breaks on Postgres, and the allowed values are enforced in validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('weight', 10, 3)->nullable()->after('stock');
            $table->string('weight_unit', 2)->default('kg')->after('weight');
            $table->decimal('length', 10, 2)->nullable()->after('weight_unit');
            $table->decimal('width', 10, 2)->nullable()->after('length');
            $table->decimal('height', 10, 2)->nullable()->after('width');
            $table->string('dimension_unit', 2)->default('cm')->after('height');
            $table->char('origin_country', 2)->default('MA')->after('dimension_unit');
            $table->string('hs_code', 12)->nullable()->after('origin_country');
            $table->string('customs_description', 255)->nullable()->after('hs_code');
            $table->boolean('is_fragile')->default(false)->after('customs_description');
            $table->unsignedTinyInteger('handling_days')->nullable()->after('is_fragile');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('weight', 10, 3)->nullable()->after('professional_price_override');
            $table->decimal('length', 10, 2)->nullable()->after('weight');
            $table->decimal('width', 10, 2)->nullable()->after('length');
            $table->decimal('height', 10, 2)->nullable()->after('width');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('hs_code', 12)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('hs_code');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['weight', 'length', 'width', 'height']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'weight', 'weight_unit', 'length', 'width', 'height',
                'dimension_unit', 'origin_country', 'hs_code',
                'customs_description', 'is_fragile', 'handling_days',
            ]);
        });
    }
};
