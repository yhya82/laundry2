<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a signed-in user to "Choose a new password" while their password is
 * temporary (new account or Admin reset) or older than the expiry. Nothing
 * else is reachable until it's changed -- only that screen and logout.
 */
class EnforcePasswordPolicy
{
    protected const ALLOWED_ROUTES = ['password.change', 'password.change.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->needsPasswordChange() && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
