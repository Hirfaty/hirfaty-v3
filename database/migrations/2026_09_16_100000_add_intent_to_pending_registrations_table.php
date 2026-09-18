<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which kind of account this signup is heading towards.
 *
 * The code step is identical for a shopper and an artisan — same table, same
 * secret, same guards — but the screen after it is not: a buyer finishes on
 * name/phone/password, while a seller also names their workshop and lands on
 * the verification queue. Carrying the intent on the pending row is what lets
 * one flow serve both without the browser getting a say in which account it
 * ends up with.
 *
 * A plain string rather than an enum: changing an enum's allowed values later
 * requires `->change()`, which needs doctrine/dbal and breaks outright on
 * Postgres. A string column plus the constants on PendingRegistration gives
 * the same guarantee where it actually matters — the validation layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            // Defaults to buyer so rows already in flight when this ships
            // finish down the branch they started on.
            $table->string('intent', 16)->default('buyer')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropColumn('intent');
        });
    }
};
