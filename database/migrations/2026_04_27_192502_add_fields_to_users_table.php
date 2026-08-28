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
            $table->string('avatar')->nullable()->after('phone_number');
            $table->text('bio')->nullable()->after('avatar');
            $table->boolean('is_banned')->default(false)->after('bio');
            $table->boolean('two_factor_enabled')->default(false);
            $table->boolean('terms_accepted')->default(false)->after('phone_number');
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_accepted');
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
