<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_SEED_EMAIL', 'admin@laundry.test');
        $password = env('ADMIN_SEED_PASSWORD', 'password');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'System Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        if (! Role::where('name', 'Admin')->where('guard_name', 'web')->exists()) {
            $this->command?->warn('Admin role not found — run PermissionsAndRolesSeeder first.');

            return;
        }

        $user->syncRoles(['Admin']);

        $this->command?->info("Seeded admin user: {$email} / {$password} (change immediately outside local dev)");
    }
}
