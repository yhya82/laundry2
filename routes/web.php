<?php

use App\Http\Controllers\ClothesCategoryController;
use App\Http\Controllers\ClothingItemController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\LaundryPackageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionPackageController;
use App\Http\Controllers\ThemeController;
use App\Support\NavItems;
use Illuminate\Support\Facades\Route;

// Sidebar destinations with a real controller now -- excluded from the
// placeholder-route loop below.
$builtRoutes = ['customers.index', 'catalog.categories', 'catalog.packages'];

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () use ($builtRoutes) {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::patch('/theme', [ThemeController::class, 'update'])->name('theme.update');

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
    });

    Route::middleware('permission:catalog.view')->group(function () {
        Route::get('/catalog/categories', [ClothesCategoryController::class, 'index'])->name('catalog.categories');
        Route::get('/catalog/categories/{category}', [ClothesCategoryController::class, 'show'])->name('catalog.categories.show');
        Route::get('/catalog/packages', [LaundryPackageController::class, 'index'])->name('catalog.packages');
    });

    Route::middleware('permission:catalog.manage')->group(function () {
        Route::post('/catalog/categories', [ClothesCategoryController::class, 'store'])->name('catalog.categories.store');
        Route::put('/catalog/categories/{category}', [ClothesCategoryController::class, 'update'])->name('catalog.categories.update');
        Route::delete('/catalog/categories/{category}', [ClothesCategoryController::class, 'destroy'])->name('catalog.categories.destroy');

        Route::post('/catalog/categories/{category}/items', [ClothingItemController::class, 'store'])->name('catalog.categories.items.store');
        Route::put('/catalog/categories/{category}/items/{item}', [ClothingItemController::class, 'update'])->name('catalog.categories.items.update');
        Route::delete('/catalog/categories/{category}/items/{item}', [ClothingItemController::class, 'destroy'])->name('catalog.categories.items.destroy');

        Route::post('/catalog/packages/laundry', [LaundryPackageController::class, 'store'])->name('catalog.packages.laundry.store');
        Route::put('/catalog/packages/laundry/{laundryPackage}', [LaundryPackageController::class, 'update'])->name('catalog.packages.laundry.update');
        Route::delete('/catalog/packages/laundry/{laundryPackage}', [LaundryPackageController::class, 'destroy'])->name('catalog.packages.laundry.destroy');

        Route::post('/catalog/packages/subscription', [SubscriptionPackageController::class, 'store'])->name('catalog.packages.subscription.store');
        Route::put('/catalog/packages/subscription/{subscriptionPackage}', [SubscriptionPackageController::class, 'update'])->name('catalog.packages.subscription.update');
        Route::delete('/catalog/packages/subscription/{subscriptionPackage}', [SubscriptionPackageController::class, 'destroy'])->name('catalog.packages.subscription.destroy');
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
