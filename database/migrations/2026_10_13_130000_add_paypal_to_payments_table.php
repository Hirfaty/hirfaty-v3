<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PayPal, alongside Payzone.
 *
 * A PayPal purchase is `orders.payment_method = 'paypal'` — a plain string, so
 * no change there. What the payments table lacks is what PayPal does
 * differently:
 *
 *   - it issues its own order id up front, which the app needs to capture the
 *     payment once the buyer approves it;
 *   - it cannot charge MAD, so `amount` / `currency` on a PayPal row are what
 *     PayPal was asked to take (EUR, USD…), and the rate that turned the
 *     purchase's MAD total into that figure is kept beside them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // PayPal's order id for this attempt. Null for Payzone.
            $table->string('provider_order_id')->nullable()->after('charge_id')->index();
            // MAD → `currency`, as used to price this attempt. Null when the
            // attempt was charged in MAD.
            $table->decimal('exchange_rate', 14, 6)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['provider_order_id']);
            $table->dropColumn(['provider_order_id', 'exchange_rate']);
        });
    }
};
