<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why the desk cancelled an order, in a form that can be translated.
 *
 * `cancellation_reason` stays the free text it always was — what a buyer or
 * seller typed, or the desk's own precisions, sent as written. The desk now
 * also picks reasons from a fixed list (OrderCancellationService::REASONS);
 * their codes go in `cancellation_tags`, so each email and page writes them
 * in the reader's own language.
 *
 * `cancellation_refund` is the desk's decision on the money for an order paid
 * online: true refunded, false kept. Null for every cancellation that did not
 * go through that choice (buyer, seller, expiry, older orders) — those keep
 * the old wording.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('cancellation_tags')->nullable()->after('cancellation_reason');
            $table->boolean('cancellation_refund')->nullable()->after('cancellation_tags');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cancellation_tags', 'cancellation_refund']);
        });
    }
};
