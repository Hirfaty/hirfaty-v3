<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admins onboard artisans they meet in person, and often have neither an
 * email nor a phone number on hand — the account is created first and the
 * contact details are filled in later. `phone_number` was already nullable;
 * this makes `email` match.
 *
 * The unique index stays: both MySQL and Postgres allow any number of NULLs
 * in a unique column, so several contactless accounts can coexist while two
 * real addresses still collide.
 *
 * An account with no email cannot log in (Fortify authenticates on email) or
 * be sent a reset link — that's the intended trade-off, and it resolves
 * itself as soon as the admin edits the user and fills the address in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows created without an address would violate NOT NULL, so give
        // them a placeholder rather than failing the rollback outright.
        DB::table('users')
            ->whereNull('email')
            ->update(['email' => DB::raw("'no-email-' || id || '@invalid.local'")]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
