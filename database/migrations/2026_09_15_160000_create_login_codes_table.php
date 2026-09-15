<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codes emailed to an account that already exists, for the "email me a code
 * instead of my password" branch of /login.
 *
 * Deliberately not folded into `pending_registrations`. That table means "no
 * user exists for this address yet" and its service's `complete()` creates
 * one — a row sitting there for an address that *does* have a user would be a
 * landmine waiting for someone to call the wrong method. Same arithmetic
 * (see Concerns\GeneratesEmailCodes), different meaning, different table.
 *
 * Keyed on user_id rather than the email, because unlike a signup there is a
 * real account to point at, and the code should die with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_codes', function (Blueprint $table) {
            $table->id();

            // Unique: a second request for the same account refreshes the one
            // row rather than leaving a trail of live codes behind it.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Hashed like the signup code — this table is readable by anyone
            // with a database dump, and the code authenticates a session.
            $table->string('code_hash');

            // Wrong-guess counter. Burns the code once it trips
            // GeneratesEmailCodes::MAX_ATTEMPTS.
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamp('expires_at');

            // Drives the resend cooldown shown as a countdown in the UI.
            $table->timestamp('last_sent_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_codes');
    }
};
