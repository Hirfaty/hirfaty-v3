<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per in-flight buyer signup: the email has been given but the account
 * does not exist yet, because it is only created once the emailed code has been
 * typed back in (see RegistrationCodeService).
 *
 * A separate table rather than a half-filled `users` row on purpose.
 * `users.email` is UNIQUE, so a signup someone abandoned at step 2 would burn
 * that address permanently — and a row with no password and no name would have
 * to be excluded by hand from every user listing, count and role check.
 *
 * Rows are deleted the moment the account is created; anything left behind is
 * an abandoned signup, safe to prune on `expires_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();

            // The signup being resumed. Unique so a second attempt on the same
            // address refreshes the existing row instead of racing a duplicate.
            $table->string('email')->unique();

            // Hashed, never stored in the clear: this table is readable by
            // anyone with a database dump, and the code is a credential that
            // stands in for proof of inbox access.
            $table->string('code_hash');

            /*
             * Secret half of the resume cookie, hashed like the code.
             *
             * Load-bearing for security, not just convenience. Resuming on the
             * email alone would let anyone type a stranger's pending address,
             * land on the details step and set their own password — taking
             * over an address they never proved they own. Resumption therefore
             * requires this token; without the cookie the code must be entered
             * again.
             */
            $table->string('resume_token_hash')->index();

            // Set when the code is accepted — i.e. step 2 is done and the
            // details step may be shown. Null means "code still outstanding".
            $table->timestamp('verified_at')->nullable();

            // Wrong-code counter. Locks the current code once it trips the
            // service's limit, so a 6-digit secret can't be brute-forced.
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamp('expires_at');

            // Drives the resend cooldown shown as a countdown in the UI.
            $table->timestamp('last_sent_at')->nullable();

            // Carried so the code email and the eventual users.locale both
            // match the locale the visitor was actually browsing in.
            $table->string('locale', 5)->default('fr');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
