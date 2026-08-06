<?php

namespace App\Support;

class NavItems
{
    /**
     * Sidebar structure per Master Document §3.1. Each item's `permission` is
     * checked against the current user via spatie's Gate integration — items
     * the user lacks permission for are simply not rendered, not hidden by a
     * role-name check.
     *
     * @return list<array{label: string, route: string, permission: ?string, icon: string, children?: list<array{label: string, route: string, permission: ?string}>}>
     */
    public static function all(): array
    {
        return [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'permission' => null, 'icon' => 'chart'],
            ['label' => 'Customers', 'route' => 'customers.index', 'permission' => 'customers.view', 'icon' => 'people'],
            ['label' => 'Orders', 'route' => 'orders.index', 'permission' => 'orders.view', 'icon' => 'clipboard'],
            ['label' => 'Collections', 'route' => 'collections.index', 'permission' => 'collections.view', 'icon' => 'truck'],
            ['label' => 'Subscriptions', 'route' => 'subscriptions.index', 'permission' => 'subscriptions.view', 'icon' => 'repeat'],
            [
                'label' => 'Clothes', 'route' => null, 'permission' => 'catalog.view', 'icon' => 'shirt',
                'children' => [
                    ['label' => 'Categories', 'route' => 'catalog.categories', 'permission' => 'catalog.view'],
                    ['label' => 'Packages', 'route' => 'catalog.packages', 'permission' => 'catalog.view'],
                    ['label' => 'Machines', 'route' => 'catalog.machines', 'permission' => 'catalog.view'],
                ],
            ],
            ['label' => 'Payments', 'route' => 'payments.index', 'permission' => 'payments.view', 'icon' => 'wallet'],
            ['label' => 'Expenses', 'route' => 'expenses.index', 'permission' => 'expenses.view', 'icon' => 'receipt'],
            ['label' => 'Damage', 'route' => 'damage.index', 'permission' => 'damage.view', 'icon' => 'alert'],
            ['label' => 'Reports', 'route' => 'reports.index', 'permission' => 'reports.view', 'icon' => 'analytics'],
        ];
    }

    /**
     * @return list<array{label: string, route: string, permission: string, icon: string}>
     */
    public static function adminOnly(): array
    {
        return [
            ['label' => 'Users & Roles', 'route' => 'users.index', 'permission' => 'users.manage', 'icon' => 'users'],
            ['label' => 'Audit Log', 'route' => 'audit.index', 'permission' => 'audit.view', 'icon' => 'log'],
            ['label' => 'Settings', 'route' => 'settings.index', 'permission' => 'settings.manage', 'icon' => 'gear'],
        ];
    }
}
