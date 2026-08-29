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
            DB::statement('alter table products drop constraint if exists products_status_check');
            DB::statement('alter table products alter column status type varchar(255)');
            DB::statement("alter table products alter column status set default 'pending_review'");
            DB::statement("alter table products add constraint products_status_check check (status in ('pending_review', 'published', 'archived', 'rejected'))");
        } else {
            Schema::table('products', function (Blueprint $table) {
                $table->enum('status', ['pending_review', 'published', 'archived', 'rejected'])
                    ->default('pending_review')
                    ->change();
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
