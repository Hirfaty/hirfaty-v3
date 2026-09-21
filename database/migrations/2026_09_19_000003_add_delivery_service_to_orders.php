<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records which carrier an order actually shipped with, and renames the fee
 * columns off the Amana-only name.
 *
 * Two additions to `orders`:
 *   - `delivery_service_id` — which carrier billed the basket. Existing rows
 *     predate the concept of services and were all Amana, so they are
 *     backfilled onto that one.
 *   - `delivery_service_name` — a snapshot, the way `shipping_address` and
 *     `variant_label` snapshot. Renaming a carrier later must not rewrite what
 *     a past order's confirmation email said it shipped by.
 *
 * `amana_fee` becomes `delivery_fee` on both `orders` and `earnings`: the fee
 * travels with the delivery, not with the carrier.
 */
return new class extends Migration
{
    public function up(): void
    {
        $amanaId = DB::table('delivery_services')->where('slug', 'amana')->value('id');
        $amanaName = DB::table('delivery_services')->where('slug', 'amana')->value('name');

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_service_id')->nullable()->nullOnDelete();
            $table->string('delivery_service_name')->nullable();
        });

        DB::table('orders')->update([
            'delivery_service_id' => $amanaId,
            'delivery_service_name' => $amanaName,
        ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('amana_fee', 'delivery_fee');
        });

        Schema::table('earnings', function (Blueprint $table) {
            $table->renameColumn('amana_fee', 'delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('delivery_fee', 'amana_fee');
        });

        Schema::table('earnings', function (Blueprint $table) {
            $table->renameColumn('delivery_fee', 'amana_fee');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_service_id']);
            $table->dropColumn(['delivery_service_id', 'delivery_service_name']);
        });
    }
};
