<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The admin now decides the order categories appear in, and "first subcategory"
 * means something on the storefront: clicking a main category lands on it.
 *
 * Two columns because they are two different lists. `categories.sort_order`
 * places a main category among the others. `category_parent.sort_order` places
 * a subcategory under one parent, and lives on the pivot because a subcategory
 * can sit under several crafts at once and hold a different place under each.
 *
 * Both are backfilled from today's order (by id), so nothing moves on deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::table('category_parent', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0);
        });

        DB::table('categories')
            ->orderBy('id')
            ->pluck('id')
            ->each(fn ($id, $index) => DB::table('categories')
                ->where('id', $id)
                ->update(['sort_order' => $index + 1]));

        DB::table('category_parent')
            ->orderBy('category_id')
            ->get()
            ->groupBy('parent_id')
            ->each(fn ($rows) => $rows
                ->values()
                ->each(fn ($row, $index) => DB::table('category_parent')
                    ->where('parent_id', $row->parent_id)
                    ->where('category_id', $row->category_id)
                    ->update(['sort_order' => $index + 1])));
    }

    public function down(): void
    {
        Schema::table('category_parent', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
