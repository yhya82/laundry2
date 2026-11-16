<?php

namespace App\Http\Middleware;

use App\Listeners\RecordAuthenticationEvents;
use App\Support\SecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the login page say WHY someone was signed out:
 *  - "admin": their browser still sends the old session id, which an Admin
 *    revoked in Settings → Security (cached by SecuritySessionController).
 *  - "idle": no session any more, but this browser had signed in and never
 *    logged out normally (the long-lived marker cookie is still there).
 * Runs after StartSession, for guests on plain page loads only.
 */
class ExplainSignOut
{
    public const REVOKED_CACHE_PREFIX = 'revoked-session:';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->user() && ! $request->expectsJson()) {
            $previousId = $request->cookies->get(config('session.cookie'));
            $reason = null;

            if ($previousId && $previousId !== $request->session()->getId() && Cache::pull(self::REVOKED_CACHE_PREFIX.$previousId)) {
                $reason = 'admin';
            } elseif ($request->cookie(RecordAuthenticationEvents::SIGNED_IN_COOKIE)) {
                $reason = 'idle';
            }

            if ($reason) {
                // Landing straight on the login page: show it now. Any other
                // page: keep it for the redirect to login that follows.
                $method = $request->routeIs('login') ? 'now' : 'flash';
                $request->session()->{$method}('signed_out_reason', $reason);
                $request->session()->{$method}('signed_out_minutes', SecurityPolicy::idleTimeoutMinutes());
                Cookie::queue(Cookie::forget(RecordAuthenticationEvents::SIGNED_IN_COOKIE));
            }
        }

        return $next($request);
    }
}
