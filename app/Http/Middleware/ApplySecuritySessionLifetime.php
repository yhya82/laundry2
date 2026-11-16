<?php

namespace App\Http\Middleware;

use App\Support\SecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idle timeout from Settings → Security. Prepended to the web group so it
 * runs before StartSession builds the session handler with the lifetime --
 * a session idle longer than this is expired and the user must log in again.
 */
class ApplySecuritySessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            config(['session.lifetime' => SecurityPolicy::idleTimeoutMinutes()]);
        } catch (\Throwable $e) {
            // Settings table unavailable (e.g. mid-migration) -- keep the config default.
        }

        return $next($request);
    }
}
