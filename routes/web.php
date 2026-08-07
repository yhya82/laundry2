<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\ClothesCategoryController;
use App\Http\Controllers\ClothingItemController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DamageRecordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LaundryPackageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriptionPackageController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WashingMachineController;
use App\Support\NavItems;
use Illuminate\Support\Facades\Route;

// Sidebar destinations with a real controller now -- excluded from the
// placeholder-route loop below.
$builtRoutes = ['customers.index', 'catalog.categories', 'catalog.packages', 'catalog.machines', 'orders.index', 'subscriptions.index', 'collections.index', 'payments.index', 'damage.index', 'expenses.index', 'reports.index', 'users.index', 'settings.index', 'audit.index'];

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () use ($builtRoutes) {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::patch('/theme', [ThemeController::class, 'update'])->name('theme.update');

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');

    // Static-path routes (/create) must be registered before the /{customer}
    // wildcard, or "create" gets swallowed as a route-model-binding lookup.
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    });

    Route::middleware('permission:customers.manage')->group(function () {
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    });

    Route::middleware('permission:customers.view')->group(function () {
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    });

    Route::middleware('permission:customers.manage')->group(function () {
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });

    Route::middleware('permission:catalog.view')->group(function () {
        Route::get('/catalog/categories', [ClothesCategoryController::class, 'index'])->name('catalog.categories');
        Route::get('/catalog/categories/{category}', [ClothesCategoryController::class, 'show'])->name('catalog.categories.show');
        Route::get('/catalog/packages', [LaundryPackageController::class, 'index'])->name('catalog.packages');
        Route::get('/catalog/machines', [WashingMachineController::class, 'index'])->name('catalog.machines');
    });

    Route::middleware('permission:catalog.manage')->group(function () {
        Route::post('/catalog/categories', [ClothesCategoryController::class, 'store'])->name('catalog.categories.store');
        Route::put('/catalog/categories/{category}', [ClothesCategoryController::class, 'update'])->name('catalog.categories.update');
        Route::delete('/catalog/categories/{category}', [ClothesCategoryController::class, 'destroy'])->name('catalog.categories.destroy');

        Route::post('/catalog/items', [ClothingItemController::class, 'storeStandalone'])->name('catalog.items.store');
        Route::post('/catalog/categories/{category}/items', [ClothingItemController::class, 'store'])->name('catalog.categories.items.store');
        Route::put('/catalog/categories/{category}/items/{item}', [ClothingItemController::class, 'update'])->name('catalog.categories.items.update');
        Route::delete('/catalog/categories/{category}/items/{item}', [ClothingItemController::class, 'destroy'])->name('catalog.categories.items.destroy');

        Route::post('/catalog/packages/laundry', [LaundryPackageController::class, 'store'])->name('catalog.packages.laundry.store');
        Route::put('/catalog/packages/laundry/{laundryPackage}', [LaundryPackageController::class, 'update'])->name('catalog.packages.laundry.update');
        Route::delete('/catalog/packages/laundry/{laundryPackage}', [LaundryPackageController::class, 'destroy'])->name('catalog.packages.laundry.destroy');

        Route::post('/catalog/packages/subscription', [SubscriptionPackageController::class, 'store'])->name('catalog.packages.subscription.store');
        Route::put('/catalog/packages/subscription/{subscriptionPackage}', [SubscriptionPackageController::class, 'update'])->name('catalog.packages.subscription.update');
        Route::delete('/catalog/packages/subscription/{subscriptionPackage}', [SubscriptionPackageController::class, 'destroy'])->name('catalog.packages.subscription.destroy');

        Route::post('/catalog/machines', [WashingMachineController::class, 'store'])->name('catalog.machines.store');
        Route::put('/catalog/machines/{washingMachine}', [WashingMachineController::class, 'update'])->name('catalog.machines.update');
        Route::post('/catalog/machines/{washingMachine}/toggle-active', [WashingMachineController::class, 'toggleActive'])->name('catalog.machines.toggleActive');
    });

    Route::middleware('permission:orders.view')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    });

    // Gated on terminal.use (not orders.manage): opening the Terminal to
    // create an order is a distinct permission from managing existing ones.
    Route::middleware('permission:terminal.use')->group(function () {
        Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    });

    Route::middleware('permission:orders.view')->group(function () {
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt'])->name('orders.receipt');
    });

    Route::middleware('permission:orders.manage')->group(function () {
        Route::post('/orders/{order}/advance', [OrderController::class, 'advance'])->name('orders.advance');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{order}/payments', [PaymentController::class, 'record'])->name('orders.payments.record');
        Route::post('/subscription-cycles/{subscriptionCycle}/payments', [PaymentController::class, 'recordForCycle'])->name('subscriptionCycles.payments.record');
    });

    // Separate from orders.manage -- who can see every order and hand work
    // out is a distinct capability from who can process one they already
    // see (see PermissionsAndRolesSeeder).
    Route::middleware('permission:orders.assign')->group(function () {
        Route::put('/orders/{order}/assign', [OrderController::class, 'assign'])->name('orders.assign');
    });

    Route::middleware('permission:subscriptions.view')->group(function () {
        Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    });

    Route::middleware('permission:subscriptions.manage')->group(function () {
        Route::get('/subscriptions/create', [SubscriptionController::class, 'create'])->name('subscriptions.create');
        Route::post('/subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::post('/subscriptions/{subscription}/pause', [SubscriptionController::class, 'pause'])->name('subscriptions.pause');
        Route::post('/subscriptions/{subscription}/resume', [SubscriptionController::class, 'resume'])->name('subscriptions.resume');
        Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
        Route::post('/subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew'])->name('subscriptions.renew');
        Route::put('/subscriptions/{subscription}/collection-type', [SubscriptionController::class, 'updateCollectionType'])->name('subscriptions.collection-type.update');
    });

    Route::middleware('permission:subscriptions.view')->group(function () {
        Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
    });

    Route::middleware('permission:collections.view')->group(function () {
        Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
    });

    Route::middleware('permission:collections.manage')->group(function () {
        Route::post('/collections/{collection}/cancel', [CollectionController::class, 'cancel'])->name('collections.cancel');
        Route::get('/collections/{collection}/collect', [CollectionController::class, 'collect'])->name('collections.collect');
    });

    Route::middleware('permission:payments.view')->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    });

    Route::middleware('permission:damage.view')->group(function () {
        Route::get('/damage', [DamageRecordController::class, 'index'])->name('damage.index');
        Route::get('/damage/{damageRecord}', [DamageRecordController::class, 'show'])->name('damage.show');
    });

    Route::middleware('permission:damage.report')->group(function () {
        Route::get('/orders/{order}/damage/create', [DamageRecordController::class, 'create'])->name('damage.create');
        Route::post('/orders/{order}/damage', [DamageRecordController::class, 'store'])->name('damage.store');
    });

    Route::middleware('permission:damage.manage')->group(function () {
        Route::post('/damage/{damageRecord}/transition', [DamageRecordController::class, 'transition'])->name('damage.transition');
        Route::post('/damage/{damageRecord}/resolve', [DamageRecordController::class, 'resolve'])->name('damage.resolve');
    });

    Route::middleware('permission:expenses.view')->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    });

    Route::middleware('permission:expenses.manage')->group(function () {
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::post('/expenses/categories', [ExpenseController::class, 'storeCategory'])->name('expenses.categories.store');
    });

    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export/revenue', [ReportController::class, 'exportRevenue'])->name('reports.export.revenue');
        Route::get('/reports/export/damage', [ReportController::class, 'exportDamage'])->name('reports.export.damage');
        Route::get('/reports/export/expenses', [ReportController::class, 'exportExpenses'])->name('reports.export.expenses');
    });

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::put('/users/{user}/password', [UserController::class, 'setPassword'])->name('users.setPassword');
        Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggleActive');
        Route::put('/users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles.update');
        Route::post('/roles', [UserController::class, 'storeRole'])->name('roles.store');
        Route::put('/roles/{role}/permissions', [UserController::class, 'updateRolePermissions'])->name('roles.permissions.update');
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    Route::middleware('permission:audit.view')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    });

    // Placeholder routes for every remaining sidebar destination. Each is
    // permission-gated now so the sidebar and access control (Phase 02) can
    // be verified end-to-end before the real screens (Phase 03+) exist.
    foreach (array_merge(NavItems::all(), NavItems::adminOnly()) as $item) {
        $targets = $item['children'] ?? [$item];

        foreach ($targets as $target) {
            if ($target['route'] === null || $target['route'] === 'dashboard' || in_array($target['route'], $builtRoutes, true)) {
                continue;
            }

            Route::get('/'.str_replace('.', '/', $target['route']), function () use ($target) {
                return view('placeholder', ['title' => $target['label'], 'permission' => $target['permission']]);
            })
                ->middleware($target['permission'] ? "permission:{$target['permission']}" : [])
                ->name($target['route']);
        }
    }
});

require __DIR__.'/auth.php'; 
  