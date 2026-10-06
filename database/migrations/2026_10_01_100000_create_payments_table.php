<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card payments (Payzone), alongside the cash-on-delivery that every order
 * used until now.
 *
 * `orders.payment_method` / `payment_status` say how a purchase settles.
 * Plain strings rather than enums: an enum's ->change() breaks on Postgres,
 * and a new provider or state should be a code change, not a migration.
 *
 * `payments` is one row per trip to the payment page, not per purchase:
 * Payzone wants a fresh `chargeId` every time the buyer is sent there, so a
 * buyer who comes back after a declined card and tries again is a second
 * row against the same `group_reference`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Every order before this one was cash on delivery.
            $table->string('payment_method', 20)->default('cod')->after('status');
            // cod: always `pending` — the courier collects, nobody records it.
            // card: `pending` until Payzone's callback says CHARGED, then `paid`,
            // or `failed` once the purchase is abandoned.
            $table->string('payment_status', 20)->default('pending')->after('payment_method');
            $table->index(['payment_method', 'payment_status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // The purchase this pays for — every order one checkout produced.
            $table->string('group_reference')->index();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 20)->default('payzone');
            // What we sent Payzone as `chargeId`; its callback answers with it as `id`.
            $table->string('charge_id')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MAD');
            // pending | paid | failed | expired | refunded
            $table->string('status', 20)->default('pending');
            // The last status Payzone reported (CHARGED, DECLINED, ...), verbatim.
            $table->string('provider_status', 40)->nullable();
            $table->string('provider_transaction_id')->nullable();
            // The card brand Payzone reports ("Visa"), for the desk.
            $table->string('card_brand', 40)->nullable();
            $table->timestamp('paid_at')->nullable();
            // The last signed notification, kept whole for disputes.
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_method', 'payment_status']);
            $table->dropColumn(['payment_method', 'payment_status']);
        });
    }
};
