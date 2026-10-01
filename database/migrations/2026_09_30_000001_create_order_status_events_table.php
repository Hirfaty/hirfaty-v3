<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What happened to an order, and when — the buyer's tracking page reads this.
 *
 * `orders.status` only ever held where an order *is*; nothing recorded when it
 * got there. One row per step (written by OrderStatusService), plus rows with
 * a null status for the desk's free-text updates between steps ("arrivé au
 * centre de tri de Casablanca"), which is where "where is my parcel" lives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Null for a note-only update: the order did not move, the desk
            // just said where it is.
            $table->string('status')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });

        /*
         * Backfill what can honestly be reconstructed: every order was placed
         * at its created_at, and reached its current status at some point —
         * delivered_at when there is one, otherwise the last time the row was
         * touched. The steps in between were never recorded and are not
         * invented here.
         */
        DB::table('orders')
            ->select(['id', 'status', 'created_at', 'updated_at', 'delivered_at', 'cancellation_reason'])
            ->orderBy('id')
            ->chunk(500, function ($orders) {
                $rows = [];

                foreach ($orders as $order) {
                    $rows[] = [
                        'order_id' => $order->id,
                        'status' => 'pending',
                        'note' => null,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->created_at,
                    ];

                    if ($order->status !== 'pending') {
                        $at = $order->delivered_at ?? $order->updated_at ?? $order->created_at;

                        $rows[] = [
                            'order_id' => $order->id,
                            'status' => $order->status,
                            'note' => $order->status === 'cancelled' ? $order->cancellation_reason : null,
                            'created_at' => $at,
                            'updated_at' => $at,
                        ];
                    }
                }

                DB::table('order_status_events')->insert($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_events');
    }
};
