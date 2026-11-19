<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An email for a buyer who signed in by phone, asked for at checkout.
 *
 * Kept apart from `email`, the sign-in identity: this address is typed, never
 * confirmed by a code, so it must not open the account (a code or a password
 * reset sent to a mistyped address would hand it to a stranger) nor claim an
 * address someone else may sign up with later. Not unique for the same reason.
 * See User::reachableEmail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('contact_email');
        });
    }
};
