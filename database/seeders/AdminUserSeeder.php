<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');

        if ($adminRoleId === null) {
            $this->command?->warn('Admin role not found — run PermissionsAndRolesSeeder first.');

            return;
        }

        DB::table('user_roles')->updateOrInsert(
            ['user_id' => $user->id, 'role_id' => $adminRoleId],
            ['updated_at' => now(), 'created_at' => now()]
        );

        $this->command?->info("Seeded admin user: {$email} / {$password} (change immediately outside local dev)");
    }
}
