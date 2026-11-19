<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The DHL Express shipment carrying a purchase abroad, and the documents DHL
 * returned for it.
 *
 * One per purchase, like `cathedis_shipments`: the desk gathers every
 * workshop's piece and hands DHL one shipment. Written by DhlShipmentService
 * when the desk ships, and kept up to date by `dhl:refresh-shipments`.
 *
 * The label and commercial invoice are kept in the database rather than on a
 * disk: they print the buyer's name, address and phone, the app container's
 * own disk does not survive a deploy, and the media bucket is served
 * publicly. They are a few dozen kilobytes each, and the nightly backup then
 * carries them too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhl_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_reference')->unique();
            // DHL's waybill number (AWB), the purchase's tracking number.
            $table->string('tracking_number')->unique();
            // One tracking number per box, as DHL numbered them.
            $table->json('piece_tracking_numbers')->nullable();
            $table->string('product_code', 3);
            $table->string('incoterm', 3);
            $table->date('planned_shipping_date');
            // The boxes as sent (kg, cm) and the goods' value in MAD: a
            // courier booked later has to describe the shipment again.
            $table->json('packages');
            $table->decimal('declared_value', 10, 2);
            // What DHL priced the shipment at for Hirfaty's account, when it said.
            $table->decimal('charge', 10, 2)->nullable();
            $table->string('charge_currency', 3)->nullable();
            $table->date('estimated_delivery_date')->nullable();
            // The courier booking, when one was made: cancellable by this number.
            $table->string('pickup_confirmation_number')->nullable();
            $table->timestamp('pickup_requested_at')->nullable();
            $table->timestamp('pickup_cancelled_at')->nullable();
            // DHL's last checkpoint (PU, AF, WC, OK…) and every checkpoint read so far.
            $table->string('status_code', 5)->nullable();
            $table->string('status_description')->nullable();
            $table->json('events')->nullable();
            $table->timestamp('synced_at')->nullable();
            // Delivered or back with us: no longer polled.
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dhl_shipment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dhl_shipment_id')->constrained()->cascadeOnDelete();
            // label, invoice, waybillDoc, receipt, pod…
            $table->string('type', 30);
            $table->string('format', 10)->default('PDF');
            // Base64, exactly as DHL sent it.
            $table->longText('content');
            $table->timestamps();

            $table->index(['dhl_shipment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dhl_shipment_documents');
        Schema::dropIfExists('dhl_shipments');
    }
};
