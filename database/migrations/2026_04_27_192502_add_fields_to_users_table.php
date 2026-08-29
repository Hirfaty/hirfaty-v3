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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone_number')->unique()->nullable()->after('last_name');
            // professional_buyer purchases in bulk off its own price list;
            // it is otherwise treated as a buyer.
            $table->enum('role', ['super_admin', 'seller', 'buyer', 'professional_buyer'])->default('buyer')->after('phone_number');
            $table->string('avatar')->nullable()->after('role');
            $table->text('bio')->nullable()->after('avatar');
            $table->boolean('is_banned')->default(false)->after('bio');
            // $table->string('two_factor_secret')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'phone', 'role',
                'avatar', 'bio', 'is_banned',
                'two_factor_secret', 'two_factor_enabled',
            ]);
        });
    }
};
