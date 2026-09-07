<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Craft categories a buyer said they were interested in during onboarding.
 *
 * Purely a preference signal — nothing gates on it, and an empty set is the
 * normal state for anyone who skipped the tour. Kept as a pivot rather than a
 * JSON column so it can be joined against when the storefront starts
 * personalising, and so a deleted category cleans up after itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One row per pairing: re-running onboarding syncs rather than
            // accumulating duplicates.
            $table->unique(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_user');
    }
};
