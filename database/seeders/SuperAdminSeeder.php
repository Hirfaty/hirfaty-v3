<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('auth.admin_default_password');

        User::firstOrCreate(
            ['email' => 'admin@hirfaty.test'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'phone_number' => '0600000000',
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("✅ Super admin created: admin@hirfaty.test / {$password}");
    }
}
