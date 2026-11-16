<?php

namespace App\Listeners;

use App\Models\LoginEvent;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Support\SecurityPolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;

/**
 * Writes the Security Center's login log from Laravel's own auth events,
 * and alerts Admins when an account gets locked. Discovered automatically
 * (handle* methods, typed by event).
 */
class RecordAuthenticationEvents
{
    /**
     * Long-lived marker that this browser had signed in, so the login page
     * can explain an idle sign-out (see App\Http\Middleware\ExplainSignOut).
     * Removed on a normal logout.
     */
    public const SIGNED_IN_COOKIE = 'lp_signed_in';

    public function __construct(protected NotificationDispatcher $notifications)
    {
    }

    public function handleLogin(Login $event): void
    {
        LoginEvent::record('login', [
            'user_id' => $event->user->getAuthIdentifier(),
            'email' => $event->user->email ?? null,
        ]);

        Cookie::queue(self::SIGNED_IN_COOKIE, '1', 60 * 24 * 30);
    }

    public function handleFailed(Failed $event): void
    {
        LoginEvent::record('failed', [
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => $event->credentials['email'] ?? null,
            'reason' => $event->user ? 'wrong_password' : 'unknown_email',
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user) {
            LoginEvent::record('logout', [
                'user_id' => $event->user->getAuthIdentifier(),
                'email' => $event->user->email ?? null,
            ]);
        }

        Cookie::queue(Cookie::forget(self::SIGNED_IN_COOKIE));
    }

    /**
     * Fires on every attempt made while locked -- the log gets one row per
     * attempt, but Admins are notified only once per lockout window.
     */
    public function handleLockout(Lockout $event): void
    {
        $email = (string) $event->request->input('email');
        $ip = $event->request->ip();
        $user = User::where('email', $email)->first();

        LoginEvent::record('lockout', [
            'user_id' => $user?->id,
            'email' => $email,
            'reason' => 'too_many_attempts',
        ]);

        $minutes = SecurityPolicy::lockoutMinutes();

        if (! Cache::add('lockout-notified:'.sha1(strtolower($email).'|'.$ip), true, now()->addMinutes($minutes))) {
            return;
        }

        $who = $user ? "{$user->name} ({$email})" : "{$email} (no matching account)";

        User::role('Admin')->where('is_active', true)->get()->each(
            fn (User $admin) => $this->notifications->toStaff(
                $admin,
                'Account locked',
                "Login locked for {$who} after ".SecurityPolicy::lockoutAttempts()." failed attempts from {$ip}. It unlocks in {$minutes} minutes."
            )
        );
    }
}
