<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An admin cataloguing an artisan's work often doesn't have the price agreed
 * yet. NULL now means "not priced": distinct from 0, which is a real decision
 * to give something away, and which the old `?? 0` fallback in
 * Admin\ProductController silently invented for every half-filled draft.
 *
 * Nothing unpriced reaches a buyer — Product::scopeStorefront() filters it out
 * of every public listing, and CheckoutService refuses to bill it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('base_price', 10, 2)->nullable()->change();
        });

        /*
         * Reclaim the drafts that went in under the old `?? 0` fallback.
         * Restricted to drafts on purpose: a *published* product at 0 might be
         * a deliberate freebie, and rewriting it to "unpriced" would pull it
         * off the storefront without anyone asking.
         */
        DB::table('products')
            ->where('status', 'draft')
            ->where('base_price', 0)
            ->update(['base_price' => null]);
    }

    public function down(): void
    {
        DB::table('products')->whereNull('base_price')->update(['base_price' => 0]);

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('base_price', 10, 2)->nullable(false)->change();
        });
    }
};
