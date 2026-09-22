<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One craft is rarely the only home for a subcategory — "Art de la table"
 * belongs under Poterie and under Dinanderie both — so the single
 * `categories.parent_id` column gives way to a pivot. Whatever parent a row
 * already had is carried across first, so the taxonomy comes out of this
 * migration looking exactly as it went in; only its shape has widened.
 *
 * A category with no rows in the pivot is a root. That is the same rule
 * `parent_id IS NULL` encoded, so "root" keeps meaning what it meant — see
 * `Category::scopeRoots()`, which is now the single place it is spelled out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_parent', function (Blueprint $table) {
            // `category_id` is the subcategory; `parent_id` a craft it sits
            // under. Cascading both ways keeps the pivot from outliving either
            // end — the old column used `set null`, which is what orphan rows
            // in the admin tree came from.
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('categories')->cascadeOnDelete();

            $table->primary(['category_id', 'parent_id']);
        });

        $existing = DB::table('categories')
            ->whereNotNull('parent_id')
            ->get(['id', 'parent_id'])
            ->map(fn ($row) => [
                'category_id' => $row->id,
                'parent_id' => $row->parent_id,
            ])
            ->all();

        if ($existing !== []) {
            DB::table('category_parent')->insert($existing);
        }

        // The constraint has to go before the column: SQLite refuses to drop a
        // column that is still named in a foreign key, and rebuilds the table
        // instead when both are asked for together.
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }

    /**
     * Puts the column back with one parent per row. A subcategory that has
     * since been given several keeps only its lowest-id parent — the column
     * cannot hold more, and rolling back is meant to restore the schema, not
     * to invent a taxonomy that fits it.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('slug');

            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        });

        DB::table('category_parent')
            ->orderBy('parent_id')
            ->get()
            ->groupBy('category_id')
            ->each(fn ($rows, $categoryId) => DB::table('categories')
                ->where('id', $categoryId)
                ->update(['parent_id' => $rows->first()->parent_id]));

        Schema::dropIfExists('category_parent');
    }
};
