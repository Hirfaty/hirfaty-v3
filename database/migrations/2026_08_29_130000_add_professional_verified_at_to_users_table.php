<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * professional_buyer is already a distinct role (see the users.role enum),
     * so this only adds the verification flag on top of it: null means "not
     * yet reviewed". Nothing currently gates on this column — a professional
     * buyer can shop immediately after registering — it exists so an admin
     * can mark an account verified (or later revoke that) whenever that check
     * gets wired in.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('professional_verified_at')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('professional_verified_at');
        });
    }
};
