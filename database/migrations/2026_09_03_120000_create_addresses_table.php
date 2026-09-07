<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved delivery addresses.
 *
 * Checkout has always taken a free-text `shipping_address` plus a `city_id`
 * and written them onto the order; that snapshot stays exactly as it is — an
 * order must keep the address it was actually shipped to, even after the
 * buyer edits or deletes the saved one. This table is only the buyer's own
 * address book, to save them retyping it.
 *
 * The shape deliberately mirrors what checkout already collects, so a saved
 * address can fill that form field for field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // "Maison", "Riad Zitoun", … purely to tell two addresses apart.
            $table->string('label')->nullable();
            // Both default to the account holder when left blank — a buyer
            // shipping to themselves shouldn't have to retype their own name.
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();

            $table->string('address_line', 500);
            $table->foreignId('city_id')->constrained();

            /*
             * At most one true row per user, enforced in AddressService rather
             * than by a constraint: a partial unique index (`WHERE is_default`)
             * is Postgres-only, and the test suite runs on SQLite.
             */
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
