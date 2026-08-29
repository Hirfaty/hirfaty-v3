<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('alter table orders drop constraint if exists orders_status_check');
            DB::statement('alter table orders alter column status type varchar(255)');
            DB::statement("alter table orders alter column status set default 'pending'");
            DB::statement("alter table orders add constraint orders_status_check check (status in ('pending', 'confirmed', 'shipped', 'delivered', 'cancelled', 'undeliverable'))");
        } else {
            Schema::table('orders', function (Blueprint $table) {
                $table->enum('status', [
                    'pending', 'confirmed', 'shipped',
                    'delivered', 'cancelled', 'undeliverable',
                ])->default('pending')->change();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
};
