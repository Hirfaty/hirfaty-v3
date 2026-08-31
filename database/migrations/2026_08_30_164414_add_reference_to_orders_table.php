<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the public-facing order reference.
 *
 * `orders.id` is a single sequence shared by every store, so showing it to a
 * buyer or seller leaks the platform's total order volume. The reference is
 * opaque and random, and becomes the route key for Order.
 */
return new class extends Migration
{
    /** Crockford base32 — no ambiguous I, L, O or U. Mirrors Order::REFERENCE_ALPHABET. */
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function up(): void
    {
        // Added nullable so existing rows survive the first statement; the
        // backfill below fills them in before the column is tightened.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('reference', 20)->nullable()->after('id');
        });

        $this->backfill();

        Schema::table('orders', function (Blueprint $table) {
            $table->string('reference', 20)->nullable(false)->change();
            $table->unique('reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn('reference');
        });
    }

    /**
     * Deliberately uses the query builder rather than the Order model: a
     * migration must keep working even if the model's generation logic later
     * changes shape.
     */
    private function backfill(): void
    {
        $used = [];

        DB::table('orders')
            ->whereNull('reference')
            ->orderBy('id')
            ->select('id')
            ->chunk(200, function ($rows) use (&$used) {
                foreach ($rows as $row) {
                    do {
                        $reference = $this->makeReference();
                    } while (
                        isset($used[$reference])
                        || DB::table('orders')->where('reference', $reference)->exists()
                    );

                    $used[$reference] = true;

                    DB::table('orders')
                        ->where('id', $row->id)
                        ->update(['reference' => $reference]);
                }
            });
    }

    private function makeReference(): string
    {
        $body = '';

        for ($i = 0; $i < 8; $i++) {
            $body .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return 'HRF-'.substr($body, 0, 4).'-'.substr($body, 4);
    }
};
