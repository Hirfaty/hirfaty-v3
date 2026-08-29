<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products carry two independent price lists: one for normal buyers
     * (base/sale) and one for professional buyers who purchase in bulk.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Professional price list. Nullable: a product may be sold to
            // normal buyers only, in which case there is no pro price.
            $table->decimal('professional_price', 10, 2)->nullable()->after('sale_price');
            $table->decimal('professional_sale_price', 10, 2)->nullable()->after('professional_price');

            // Bulk minimum enforced at checkout for professional buyers.
            $table->unsignedInteger('min_order_qty_professional')->default(1)->after('stock');

            // Which audience may see/buy this product at all.
            $table->enum('visibility', ['individual', 'professional', 'both'])
                ->default('both')
                ->after('min_order_qty_professional');

            // Denormalised flag so listings can skip loading variants.
            $table->boolean('has_variants')->default(false)->after('visibility');

            $table->string('sku')->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'professional_price',
                'professional_sale_price',
                'min_order_qty_professional',
                'visibility',
                'has_variants',
            ]);
        });

        // Unique indexes must go before the column on MariaDB.
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn('sku');
        });
    }
};
