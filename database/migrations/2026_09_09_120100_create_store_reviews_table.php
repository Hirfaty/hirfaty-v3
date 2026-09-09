<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A buyer's rating of the shop that fulfilled one delivered order.
 *
 * Separate from `product_reviews` because they answer different questions: the
 * product review is about the piece and follows it wherever it is listed, the
 * shop review is about *this* delivery — packing, timing, whether it matched
 * the description. Folding both into one table behind a `type` column would
 * mean a nullable `product_id` on every shop row and a rating average that has
 * to remember to filter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();

            $table->timestamps();

            /*
             * Scoped to the order rather than to the shop: each delivery is a
             * distinct service experience, so a buyer who orders from the same
             * artisan three times can rate all three. Checkout already splits
             * a basket into one order per store, so an order maps to exactly
             * one shop and this can never collide within a single purchase.
             */
            $table->unique(['user_id', 'order_id']);

            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_reviews');
    }
};
