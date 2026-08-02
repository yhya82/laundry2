<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsAndRolesSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private array $permissionsByModule = [
        'customers' => ['customers.view', 'customers.manage'],
        'catalog' => ['catalog.view', 'catalog.manage'],
        'terminal' => ['terminal.use'],
        'orders' => ['orders.view', 'orders.manage'],
        'subscriptions' => ['subscriptions.view', 'subscriptions.manage'],
        'collections' => ['collections.view', 'collections.manage'],
        'payments' => ['payments.view', 'payments.manage'],
        'expenses' => ['expenses.view', 'expenses.manage'],
        'damage' => ['damage.view', 'damage.report', 'damage.manage'],
        'reports' => ['reports.view'],
        'settings' => ['settings.manage'],
        'users' => ['users.manage'],
        'audit' => ['audit.view'],
    ];

    /**
     * Laundry-role staff per Master Document §5: queue + status updates + basic
     * customer info + damage reporting. Explicitly no financial reports, pricing,
     * expenses, or user/settings management.
     *
     * @var list<string>
     */
    private array $laundryPermissions = [
        'customers.view',
        'catalog.view',
        'terminal.use',
        'orders.view',
        'orders.manage',
        'subscriptions.view',
        'collections.view',
        'collections.manage',
        'damage.view',
        'damage.report',
    ];

    public function run(): void
    {
        $now = now();

        foreach ($this->permissionsByModule as $module => $names) {
            foreach ($names as $name) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $name],
                    ['module' => $module, 'updated_at' => $now, 'created_at' => $now]
                );
            }
        }

        DB::table('roles')->updateOrInsert(
            ['name' => 'Admin'],
            ['description' => 'Full access across all modules', 'is_system' => true, 'updated_at' => $now, 'created_at' => $now]
        );
        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');

        DB::table('roles')->updateOrInsert(
            ['name' => 'Laundry'],
            ['description' => 'Queue, status updates, basic customer info, damage reporting', 'is_system' => true, 'updated_at' => $now, 'created_at' => $now]
        );
        $laundryRoleId = DB::table('roles')->where('name', 'Laundry')->value('id');

        $allPermissionIds = DB::table('permissions')->pluck('id')->all();
        $this->syncRolePermissions($adminRoleId, $allPermissionIds);

        $laundryPermissionIds = DB::table('permissions')
            ->whereIn('name', $this->laundryPermissions)
            ->pluck('id')
            ->all();
        $this->syncRolePermissions($laundryRoleId, $laundryPermissionIds);
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        $now = now();

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['updated_at' => $now, 'created_at' => $now]
            );
        }
    }
}
