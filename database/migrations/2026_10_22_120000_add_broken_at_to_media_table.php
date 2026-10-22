<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the stored file behind a media row was last found unreadable — set and
 * cleared only by MediaHealthService (`php artisan media:check`, nightly).
 *
 * A stored flag rather than a live check because a lost SeaweedFS object does
 * not fail fast: the filer still answers with its size, then the body stalls
 * for ~13s. The admin product filter could never wait on that per row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->timestamp('broken_at')->nullable()->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('broken_at');
        });
    }
};
