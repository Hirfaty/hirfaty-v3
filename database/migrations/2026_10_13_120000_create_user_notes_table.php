<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The desk's private notes about an account — "rappelé le 12/10, envoie les
 * photos lundi", "préfère WhatsApp". Read and written only from the admin
 * user pages; never shown to the person the note is about.
 *
 * One row per note rather than a single text column on `users`, so several
 * admins can add to the same account without overwriting each other, and
 * each note keeps who wrote it and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notes', function (Blueprint $table) {
            $table->id();
            // The account the note is about — notes go with it.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The admin who wrote it. Kept (nulled) if that admin is removed,
            // so the note itself is not lost with them.
            $table->foreignId('author_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notes');
    }
};
