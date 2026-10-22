<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When MediaHealthService last read the file behind a media row, healthy or
 * not. The admin products list shows the latest one next to "Images
 * cassées", so an empty filter can be told apart from a check that never ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->timestamp('checked_at')->nullable()->after('broken_at');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('checked_at');
        });
    }
};
