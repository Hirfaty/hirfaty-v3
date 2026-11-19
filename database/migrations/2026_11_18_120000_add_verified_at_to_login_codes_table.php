<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sign-in email now carries a one-click link beside the code. Opened on
 * another device and confirmed there, it marks the code `verified_at`; the
 * window still on the code screen sees that and signs in by itself (see
 * LoginCodeService::consumeVerified).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_codes', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('last_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('login_codes', function (Blueprint $table) {
            $table->dropColumn('verified_at');
        });
    }
};
