<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carries the chosen variant through the purchase path.
     *
     * product_variants has existed (with its own stock and price overrides)
     * since 2026_08_28, and the admin can author variants — but nothing
     * downstream knew about them: the cart could not record which variant was
     * picked, and checkout validated against products.stock, which is forced
     * to 0 for a variant product. The net effect was that variant products
     * could not be bought at all.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // Nullable: a product without variants has no variant to point at.
            // Cascade — a cart line for a deleted variant is meaningless, and
            // a cart is disposable (unlike an order, below).
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        /*
         * The old unique(cart_id, product_id) actively blocked the feature:
         * two variants of the same product are two legitimate cart lines.
         *
         * Caveat worth knowing: on Postgres, MySQL and SQLite alike a UNIQUE
         * index treats NULLs as distinct, so this does NOT stop duplicate
         * rows for a *non-variant* product (product_variant_id IS NULL). The
         * portable ways to close that are all bad (partial indexes are
         * Postgres-only; a sentinel 0 breaks the FK), so the no-variant case
         * stays guarded in application code — CartController looks up the
         * exact (product, variant) pair before inserting. This index is the
         * backstop for the variant case, which it does cover.
         */
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'product_id']);
            $table->unique(['cart_id', 'product_id', 'product_variant_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            /*
             * nullOnDelete, NOT cascade: an order line is financial history.
             * A seller deleting last season's "Rouge / M" must not delete the
             * record that it was once sold — that would silently rewrite past
             * orders (and the earnings derived from them).
             */
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained()
                ->nullOnDelete();

            /*
             * Snapshot of the variant's options at purchase time, e.g.
             * "Rouge / M". Denormalised on purpose: it is what makes the line
             * above survivable. Options are editable and variants deletable,
             * so reading the label live off product_variants would let an
             * order retroactively claim a different configuration than the
             * one the buyer actually paid for.
             */
            $table->string('variant_label')->nullable()->after('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn('variant_label');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'product_id', 'product_variant_id']);
            $table->dropConstrainedForeignId('product_variant_id');
            $table->unique(['cart_id', 'product_id']);
        });
    }
};
