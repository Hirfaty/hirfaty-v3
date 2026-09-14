<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everyone who asked to hear from the souk.
 *
 * Deliberately not a foreign key onto `users`: the whole point of a footer
 * signup is that it works for a visitor who has not registered, and someone
 * who subscribes today and creates an account next month is still the same
 * mailbox. Matching the two up is a reporting question, not a schema one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();

            /*
             * Unique, and always written lower-cased by NewsletterService — a
             * second signup from the same mailbox has to land on the existing
             * row rather than add another, or the day an unsubscribe is built
             * it would clear one row and leave the duplicates still on the
             * list. Case matters here because `A@b.com` and `a@b.com` are the
             * same inbox at every provider anyone actually uses.
             */
            $table->string('email')->unique();

            /*
             * The language they were reading the site in when they signed up,
             * so whatever eventually sends this list can send it in that
             * language. Three locales share one table; without this the only
             * options later are guessing or mailing everybody in French.
             */
            $table->string('locale', 5)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
