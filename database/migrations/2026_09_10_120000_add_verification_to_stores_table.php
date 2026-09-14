<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin review for shops, mirroring the one products already go through.
     *
     * `verification_status` is a NEW column rather than an extension of the
     * existing `status` enum, for two reasons:
     *
     *  - The two answer different questions. `status` (active/inactive) is
     *    "is this shop switched on", which a seller or admin may toggle at any
     *    time; verification is "has a human looked at it", which happens once.
     *    Folding them together makes "an approved shop the seller has paused"
     *    unrepresentable.
     *  - Widening a Postgres enum via `->change()` is exactly the operation
     *    that has broken a migration on this project before, and production is
     *    Postgres while local/CI are SQLite. A plain string column behaves
     *    identically on all three.
     *
     * `submitted_at`/`approved_at`/`approved_by`/`rejection_reason` have been
     * listed in Store::$fillable since the model was written but were never
     * migrated — anything mass-assigning them would have thrown "unknown
     * column". They finally exist here.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('verification_status')->default('pending')->after('status');
            $table->timestamp('submitted_at')->nullable()->after('verification_status');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('approved_by');

            $table->index('verification_status');
        });

        /*
         * Shops that already existed were live before review was a concept.
         * Defaulting them to `pending` would retroactively pull every seller's
         * products offline and drop them behind a review queue nobody asked
         * for, so they are grandfathered in as approved. Only shops created
         * from here on start pending.
         */
        DB::table('stores')->update([
            'verification_status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropIndex(['verification_status']);
            $table->dropColumn([
                'verification_status',
                'submitted_at',
                'approved_at',
                'rejection_reason',
            ]);
        });
    }
};
