<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ThemeController;
use App\Support\NavItems;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::patch('/theme', [ThemeController::class, 'update'])->name('theme.update');

    // Placeholder routes for every sidebar destination beyond the dashboard.
    // Each is permission-gated now so the sidebar and access control (Phase 02)
    // can be verified end-to-end before the real screens (Phase 03+) exist.
    foreach (array_merge(NavItems::all(), NavItems::adminOnly()) as $item) {
        $targets = $item['children'] ?? [$item];

        foreach ($targets as $target) {
            if ($target['route'] === null || $target['route'] === 'dashboard') {
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
