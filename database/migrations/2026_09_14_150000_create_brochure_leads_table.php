<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everyone who asked for the brochure.
 *
 * Like `newsletter_subscribers`, deliberately not a foreign key onto `users`:
 * the point of the homepage brochure form is that a visitor who has never
 * registered can fill it in. Someone who downloads today and signs up next
 * month is still the same mailbox, and matching the two up is a reporting
 * question rather than a schema one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brochure_leads', function (Blueprint $table) {
            $table->id();

            $table->string('first_name');
            $table->string('last_name');

            /*
             * Unique, and always written lower-cased by `BrochureLeadService`.
             * A lead list wants one row per person: someone who downloads the
             * brochure again from another device — or next quarter, with a new
             * job title — should update the row that is already there rather
             * than add a near-duplicate for whoever reads this table to
             * de-duplicate by hand later.
             */
            $table->string('email')->unique();

            /*
             * Plain strings rather than an `enum` column. The set is enforced
             * by `BrochureLeadRequest` against the constants on the model, and
             * these are exactly the kind of marketing categories that get
             * added to — on Postgres, widening an `enum` afterwards means an
             * `enum()->change()`, which doctrine/dbal cannot express and which
             * has already bitten this project once.
             *
             * `seller_type` is null for buyers: the question is only asked of
             * people who said they sell, so an empty string here would be
             * indistinguishable from "a seller who skipped it".
             */
            $table->string('type', 20);
            $table->string('seller_type', 20)->nullable();

            /*
             * The language they were reading the site in, so whatever
             * eventually mails this list can mail them in it. Same reasoning as
             * `newsletter_subscribers.locale`.
             */
            $table->string('locale', 5)->nullable();

            /*
             * How many times this person has come back for the brochure. The
             * unique index above means repeat requests do not show up as extra
             * rows, and without a counter that interest would be invisible —
             * `updated_at` alone only remembers the most recent one.
             */
            $table->unsignedInteger('downloads_count')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brochure_leads');
    }
};
