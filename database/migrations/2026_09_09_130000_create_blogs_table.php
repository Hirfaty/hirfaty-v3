<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            // The admin who wrote it. Kept nullable so removing a staff account
            // never takes their published articles down with it.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            // Tiptap HTML. Sanitised on the way in by BlogService.
            $table->longText('content')->nullable();
            // A plain string rather than an enum: Postgres cannot ALTER an enum
            // in place, so every future status would need a table rewrite.
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // The public listing filters on status and orders by published_at.
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
