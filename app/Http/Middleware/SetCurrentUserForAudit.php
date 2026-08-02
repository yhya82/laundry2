<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Populates the @current_user_id session variable the audit/business-rule
 * triggers read (see database/migrations/2025_01_02_000001_create_laundry_triggers.php).
 * Without this, every trigger-driven activity_log/order_status_history row
 * records a NULL causer -- correct but useless for "who did this."
 */
class SetCurrentUserForAudit
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($userId = $request->user()?->id) {
            DB::statement('SET @current_user_id = ?', [$userId]);
        }

        return $next($request);
    }
}
