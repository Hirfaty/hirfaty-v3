<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The parcel Cathedis carries for a purchase.
 *
 * One per purchase, not per order: the desk gathers every workshop's piece
 * and hands Cathedis one parcel (see Admin\OrderController's docblock), so the
 * row is keyed on the purchase — `group_reference`, or the order's own
 * reference for an order placed before grouping existed. Written by
 * CathedisShipmentService when the desk ships, and kept up to date from
 * Cathedis by `cathedis:refresh-deliveries`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cathedis_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_reference')->unique();
            // The delivery's id in Cathedis's ERP, and its printed code (LD000536382).
            $table->unsignedBigInteger('delivery_id')->unique();
            $table->string('code');
            // What was asked of Cathedis: the cash its courier collects (0 when
            // paid online), how, and the weight bracket.
            $table->decimal('amount', 10, 2);
            $table->string('payment_type');
            $table->string('range_weight');
            // Cathedis's last word: the status name ("Mise En Distribution"),
            // its type (PENDING, SHIPPED, REJECTED, STANDBY) and whether it has
            // paid the cash it collected back to Hirfaty (unpaid / paid).
            $table->string('delivery_status')->nullable();
            $table->string('delivery_status_type')->nullable();
            $table->string('payment_status')->nullable();
            $table->timestamp('pickup_requested_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            // Delivered or back with us: no longer polled.
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cathedis_shipments');
    }
};
