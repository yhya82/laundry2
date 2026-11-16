<?php

namespace App\Providers;

use App\Support\SecurityPolicy;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Settings → Security's password policy, for every password form that
        // uses Password::defaults(). A closure, so the settings are only read
        // when a password is actually being validated.
        Password::defaults(fn () => SecurityPolicy::passwordRule());
    }
}
