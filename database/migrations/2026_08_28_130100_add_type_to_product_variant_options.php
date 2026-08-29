<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Options are captured differently per kind in the admin UI:
     *  - color: a label plus a swatch picked from a palette
     *  - size:  a label only (S, M, 42, ...)
     *  - other: a free name/value pair
     *
     * Storing the kind lets the storefront render swatches instead of text.
     */
    public function up(): void
    {
        Schema::table('product_variant_options', function (Blueprint $table) {
            $table->enum('type', ['color', 'size', 'other'])
                ->default('other')
                ->after('product_variant_id');

            // Only meaningful for `color`; holds a #rrggbb swatch.
            $table->string('color_hex', 7)->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('product_variant_options', function (Blueprint $table) {
            $table->dropColumn(['type', 'color_hex']);
        });
    }
};
