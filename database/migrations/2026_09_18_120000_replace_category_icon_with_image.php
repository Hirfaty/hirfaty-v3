<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Category emoji icons are replaced by a picture, uploaded by an admin and
 * shown on the homepage tiles. Only top-level categories carry one — see
 * CategoryService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // A storage path on the default disk, not a URL: the URL is
            // derived (Category::imageUrl) so the file can be deleted later.
            $table->string('image')->nullable()->after('parent_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('icon')->nullable();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
