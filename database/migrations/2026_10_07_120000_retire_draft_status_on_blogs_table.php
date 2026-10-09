<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Articles are now either published or archived — there is no draft stage.
 *
 * Existing drafts become archived rather than published: they were written to
 * stay hidden, and archived is the one remaining status the public cannot see.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('blogs')->where('status', 'draft')->update(['status' => 'archived']);

        Schema::table('blogs', function (Blueprint $table) {
            $table->string('status')->default('published')->change();
        });
    }

    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
        });
    }
};
