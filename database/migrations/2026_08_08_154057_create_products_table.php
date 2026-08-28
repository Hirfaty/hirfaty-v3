<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('sku')->unique();

            $table->decimal('base_price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->decimal('professional_price', 10, 2)->nullable();
            $table->decimal('professional_sale_price', 10, 2)->nullable();

            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('min_order_qty_professional')->default(1);

            $table->enum('visibility', ['individual', 'professional', 'both'])->default('both');
            $table->enum('status', ['draft', 'pending_review', 'published', 'archived', 'rejected'])
                  ->default('pending_review');

            $table->boolean('has_variants')->default(false);

            $table->decimal('weight', 8, 2)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();

            $table->decimal('average_rating', 3, 2)->default(0.00);

            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
