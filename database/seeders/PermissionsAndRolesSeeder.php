<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsAndRolesSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private array $permissionsByModule = [
        'customers' => ['customers.view', 'customers.manage'],
        'catalog' => ['catalog.view', 'catalog.manage'],
        'terminal' => ['terminal.use'],
        // orders.assign is deliberately its own permission, separate from
        // orders.manage -- it's who can see every order and hand work out
        // (assignment scoping's "sees everything" tier), not who can
        // process one they can already see. Laundry needs orders.manage to
        // do its job but should stay scoped once assignment is on, so it's
        // not in laundryPermissions below.
        'orders' => ['orders.view', 'orders.manage', 'orders.assign'],
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
        foreach ($this->permissionsByModule as $module => $names) {
            foreach ($names as $name) {
                Permission::updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['module' => $module]
                );
            }
        }

        $admin = Role::updateOrCreate(
            ['name' => 'Admin', 'guard_name' => 'web'],
            ['description' => 'Full access across all modules', 'is_system' => true]
        );
        $admin->syncPermissions(Permission::all());

        $laundry = Role::updateOrCreate(
            ['name' => 'Laundry', 'guard_name' => 'web'],
            ['description' => 'Queue, status updates, basic customer info, damage reporting', 'is_system' => true]
        );
        $laundry->syncPermissions($this->laundryPermissions);
    }
}
