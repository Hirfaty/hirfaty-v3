<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A buyer's rating of one product they actually received.
 *
 * Named `product_reviews` rather than `reviews` on purpose: `products` already
 * carries `reviewed_by`/`reviewed_at`, which is the *admin* moderation stamp on
 * a listing. A bare `reviews` table next to those columns would read as the
 * same concept and is exactly the kind of ambiguity that gets one wired into
 * the other later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            /*
             * The purchase this review is evidence of. Kept even though
             * eligibility is re-checked server-side on write: without it a
             * review is unattributable to a delivery, so a later audit (or a
             * "verified purchase" badge, once reviews are shown) has nothing
             * to stand on.
             *
             * Cascades: deleting the order deletes the proof, so the review
             * goes with it rather than being left unverifiable.
             */
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // 1–5. Tiny int rather than decimal: half-stars are a display
            // choice on the *average*, never on a single submission.
            $table->unsignedTinyInteger('rating');

            $table->text('comment')->nullable();

            $table->timestamps();

            /*
             * One review per buyer per product, not per order item. A buyer
             * who orders the same piece twice is still one opinion — per-item
             * would let a repeat customer weight the average several times
             * over, which is the cheapest way to game a rating.
             *
             * The submit path upserts against this, so a second purchase
             * edits the existing review rather than being rejected.
             */
            $table->unique(['user_id', 'product_id']);

            // Averages are recomputed per product on every write.
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
