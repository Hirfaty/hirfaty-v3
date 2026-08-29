<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A variant is one concrete purchasable combination of a product's options
     * (e.g. colour=red + size=M). It carries its own stock, and may override
     * either price list; a null override means "inherit the product price".
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('sku')->nullable()->unique();
            $table->unsignedInteger('stock')->default(0);

            $table->decimal('price_override', 10, 2)->nullable();
            $table->decimal('professional_price_override', 10, 2)->nullable();

            // Ordering for the storefront selector.
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        // Named `options` rather than `attributes`: an Eloquent relation called
        // `attributes()` would collide with the model's internal $attributes.
        Schema::create('product_variant_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();

            // e.g. name="Couleur", value="Rouge"
            $table->string('name');
            $table->string('value');

            // A variant cannot define the same option twice.
            $table->unique(['product_variant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_options');
        Schema::dropIfExists('product_variants');
    }
};
