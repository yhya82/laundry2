<?php

use App\Http\Middleware\ApplySecuritySessionLifetime;
use App\Http\Middleware\EnforcePasswordPolicy;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ExplainSignOut;
use App\Http\Middleware\SetCurrentUserForAudit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Idle timeout from Settings → Security -- must run before StartSession.
        $middleware->prependToGroup('web', ApplySecuritySessionLifetime::class);

        $middleware->appendToGroup('web', EnsureUserIsActive::class);
        $middleware->appendToGroup('web', SetCurrentUserForAudit::class);
        // Temporary / expired password -> "Choose a new password" first.
        $middleware->appendToGroup('web', EnforcePasswordPolicy::class);
        // Tells the login page why someone was signed out (idle / by an Admin).
        $middleware->appendToGroup('web', ExplainSignOut::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // withoutOverlapping guards against a slow backup still running when
        // the next day's run fires; onOneServer matters once this runs on
        // more than one app node.
        $schedule->command('backup:run')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
        $schedule->command('backup:cleanup')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
        $schedule->command('health:monitor')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
