<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivating a user (see UserController::toggleActive()) needs to take
 * effect immediately, not just block their next login -- an existing
 * session would otherwise stay valid until it expires on its own.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->is_active === false) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account has been deactivated. Contact an administrator.']);
        }

        return $next($request);
    }
}
