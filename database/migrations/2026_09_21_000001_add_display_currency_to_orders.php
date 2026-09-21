<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What currency the buyer was reading when they ordered.
 *
 * The order still settles in MAD — `total_amount` and every figure beside it
 * are unchanged, and the earnings ledger is untouched. These two columns
 * exist so the desk can answer "what did this buyer actually see?" when a
 * French customer queries a total, which is otherwise unanswerable once the
 * rate has moved.
 *
 * `display_rate` is a snapshot for exactly that reason: storing only the code
 * and re-converting later would show today's rate against a months-old order
 * and quietly misreport what was quoted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Nullable: every order placed before this migration has no
            // answer, and inventing MAD for them would claim a fact we don't
            // have. Null reads as "unknown / before we tracked it".
            $table->string('display_currency', 3)->nullable()->after('total_amount');

            // Enough precision for a weak currency against MAD without
            // rounding the rate itself into nonsense.
            $table->decimal('display_rate', 14, 6)->nullable()->after('display_currency');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['display_currency', 'display_rate']);
        });
    }
};
