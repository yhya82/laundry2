<?php

namespace App\Support;

use App\Http\Middleware\ExplainSignOut;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signs devices out by deleting their rows from the database `sessions`
 * table. Each revoked id is remembered briefly so the login page can say
 * "Your session was ended by an administrator" (ExplainSignOut).
 *
 * A device that ticked "Remember me" would silently log back in with its
 * saved token, so signing out a USER also resets their remember token --
 * that's the only true sign-out for such devices. A single session can't
 * reset just its own device's token (the token is per user, not per device).
 */
class SessionRevoker
{
    public static function revokeSession(string $sessionId): ?int
    {
        $userId = DB::table('sessions')->where('id', $sessionId)->value('user_id');

        self::markRevoked([$sessionId]);
        DB::table('sessions')->where('id', $sessionId)->delete();

        return $userId;
    }

    /** All of one user's devices, including "Remember me" ones. */
    public static function revokeUser(User $user): int
    {
        $ids = DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all();

        self::markRevoked($ids);
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        return count($ids);
    }

    /**
     * Everyone except the current session: every other signed-in device
     * (the caller's own other devices included) and every other user's
     * remember token.
     */
    public static function revokeAllExcept(string $keepSessionId, User $keepUser): int
    {
        $ids = DB::table('sessions')->whereNotNull('user_id')->where('id', '!=', $keepSessionId)->pluck('id')->all();

        self::markRevoked($ids);
        DB::table('sessions')->whereIn('id', $ids)->delete();

        User::where('id', '!=', $keepUser->id)->each(
            fn (User $user) => $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly()
        );

        return count($ids);
    }

    protected static function markRevoked(array $ids): void
    {
        $until = now()->addMinutes(max(60, SecurityPolicy::idleTimeoutMinutes()));

        foreach ($ids as $id) {
            Cache::put(ExplainSignOut::REVOKED_CACHE_PREFIX.$id, true, $until);
        }
    }
}
