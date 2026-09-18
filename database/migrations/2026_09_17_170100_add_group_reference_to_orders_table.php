<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ties back together the orders one checkout split apart.
 *
 * `CheckoutService` splits a basket into one `Order` per store, because each
 * artisan is paid and fulfils separately. But the buyer made one purchase and
 * Hirfaty delivers it as one job, so the admin desk needs to see it whole:
 * every product, every workshop, one destination, usually one parcel.
 *
 * A shared string on the orders rather than a `purchases` table — nothing
 * belongs on the group that isn't already on its orders (same buyer, same
 * address, same moment), so a parent row would carry no data of its own.
 *
 * Nullable because orders placed before this column existed have no group;
 * anything reading it treats "no group" as a group of one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('group_reference')->nullable()->after('reference');
            $table->index('group_reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['group_reference']);
            $table->dropColumn('group_reference');
        });
    }
};
