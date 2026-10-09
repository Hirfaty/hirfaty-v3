<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which admin opened the account, so the users table can tell an artisan the
 * desk signed up in person from one who registered on their own.
 *
 * null = the person signed up themselves (code signup, seller signup, Google).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()
                ->after('role')
                ->constrained('users')
                ->nullOnDelete();
        });

        /*
         * Every self-signup path stamps `terms_accepted` (the checkbox, or the
         * button that implies it); Admin\UserController::store never has. So a
         * non-admin account without it was made by hand. Which admin did it
         * was never recorded — the oldest super admin stands in for the desk.
         */
        $admin = DB::table('users')->where('role', 'super_admin')->orderBy('id')->value('id');

        if ($admin) {
            DB::table('users')
                ->where('role', '!=', 'super_admin')
                ->where('terms_accepted', false)
                ->update(['created_by' => $admin]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
