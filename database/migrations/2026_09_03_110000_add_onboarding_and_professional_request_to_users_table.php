<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two related additions.
 *
 * 1. `onboarded_at` — a new buyer is walked through a short, skippable
 *    intro after verifying their email. The column records that they've been
 *    through it (completed *or* skipped are the same thing here: don't ask
 *    again), so the tour can't reappear on every login.
 *
 * 2. The professional-account request. Trade pricing is not self-serve — a
 *    riad or hotel asks, an admin reviews, and only then does the account
 *    become a professional_buyer. The request therefore lives in its own
 *    columns and deliberately does NOT touch `role` or
 *    `professional_verified_at`; those are written by the admin's approval,
 *    which is the whole point of the gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarded_at')->nullable()->after('locale');

            /*
             * null = never asked, then pending / approved / declined.
             * A plain string rather than an enum: Postgres cannot ALTER an
             * enum in place, so adding a fourth state later would mean a
             * table rewrite (see the notes on products.status).
             */
            $table->string('professional_request_status', 20)
                ->nullable()
                ->after('professional_verified_at');
            $table->text('professional_request_message')->nullable()
                ->after('professional_request_status');
            $table->timestamp('professional_requested_at')->nullable()
                ->after('professional_request_message');

            // Who closed the request and when — the same audit shape products
            // use for moderation (reviewed_by / reviewed_at).
            $table->timestamp('professional_reviewed_at')->nullable()
                ->after('professional_requested_at');
            $table->foreignId('professional_reviewed_by')->nullable()
                ->after('professional_reviewed_at')
                ->constrained('users')
                ->nullOnDelete();
            // Shown to the buyer, so a refusal isn't a silent dead end.
            $table->text('professional_decline_reason')->nullable()
                ->after('professional_reviewed_by');

            // The admin list filters on "who is waiting for me?".
            $table->index('professional_request_status');
        });

        /*
         * Existing accounts have already been using the site, so don't drop
         * them into a "welcome, you're new here" tour on their next visit.
         * Backfilled to their signup date rather than now(), which is closer
         * to true and keeps the column meaningful if it's ever reported on.
         */
        DB::table('users')->update(['onboarded_at' => DB::raw('created_at')]);

        /*
         * Trade accounts that predate this flow were set by an admin by hand.
         * Mark them approved so they don't read as "never asked" — and so an
         * already-verified professional isn't offered the request form again.
         */
        DB::table('users')
            ->where('role', 'professional_buyer')
            ->update([
                'professional_request_status' => 'approved',
                'professional_requested_at' => DB::raw('created_at'),
                'professional_reviewed_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['professional_reviewed_by']);
            $table->dropIndex(['professional_request_status']);
            $table->dropColumn([
                'onboarded_at',
                'professional_request_status',
                'professional_request_message',
                'professional_requested_at',
                'professional_reviewed_at',
                'professional_reviewed_by',
                'professional_decline_reason',
            ]);
        });
    }
};
