<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passwordless sign-in by email *or* phone, and a basket for visitors who have
 * not signed in.
 *
 *  - `users.password` becomes optional: a shopper signs in with a one-time
 *    code and never sets one. Admins keep theirs (LoginCodeService refuses
 *    them a code).
 *  - `users.phone_verified_at` marks a number proven by an SMS code. Only a
 *    verified number signs anyone in: `phone_number` has always been free
 *    text typed at signup or checkout, and a typo there must never hand the
 *    account to whoever owns the mistyped number.
 *  - `pending_registrations.phone`: a signup can start from a number instead
 *    of an address, so either column may be the one that is set.
 *  - `login_codes.channel`: whether the code went by email or SMS, so a resend
 *    goes the same way.
 *  - `carts.guest_token_hash`: a visitor's basket, found by the cookie that
 *    holds the token (CartService). `user_id` is null until they sign in, when
 *    the basket is folded into their account's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->timestamp('phone_verified_at')->nullable()->after('phone_number');
        });

        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone', 20)->nullable()->unique()->after('email');
        });

        Schema::table('login_codes', function (Blueprint $table) {
            $table->string('channel', 10)->default('email')->after('user_id');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('guest_token_hash', 64)->nullable()->unique()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropUnique(['guest_token_hash']);
            $table->dropColumn('guest_token_hash');
        });

        // Guest baskets have no owner to keep them for.
        DB::table('carts')->whereNull('user_id')->delete();

        Schema::table('carts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('login_codes', function (Blueprint $table) {
            $table->dropColumn('channel');
        });

        DB::table('pending_registrations')->whereNull('email')->delete();

        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
            $table->string('email')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_verified_at');
        });
        // `password` stays nullable on the way down: accounts made without one
        // since cannot be given a value here.
    }
};
