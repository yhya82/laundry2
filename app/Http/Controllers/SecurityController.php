<?php

namespace App\Http\Controllers;

use App\Models\LoginEvent;
use App\Models\User;
use App\Support\SessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Settings → Security actions on signed-in devices. Admin role only (route
 * middleware). Every sign-out is written to the login log with the Admin
 * who did it. The Security tab's data itself is built by
 * SettingsController::securityData().
 */
class SecurityController extends Controller
{
    public function revokeSession(Request $request, string $sessionId): RedirectResponse
    {
        if ($sessionId === $request->session()->getId()) {
            return back()->withErrors(['security' => 'That\'s this device. Use Log out instead.']);
        }

        $userId = SessionRevoker::revokeSession($sessionId);

        if ($userId === null) {
            return back()->withErrors(['security' => 'That session has already ended.']);
        }

        LoginEvent::record('sessions_revoked', [
            'user_id' => $userId,
            'email' => User::find($userId)?->email,
            'actor_id' => auth()->id(),
            'reason' => 'one_device',
        ]);

        return back()->with('status', 'Device signed out.');
    }

    public function revokeUserSessions(Request $request, User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['security' => 'You can\'t sign out all of your own devices from here. Use "Sign out everyone except me".']);
        }

        $count = SessionRevoker::revokeUser($user);

        LoginEvent::record('sessions_revoked', [
            'user_id' => $user->id,
            'email' => $user->email,
            'actor_id' => auth()->id(),
            'reason' => 'all_devices',
        ]);

        return back()->with('status', "{$user->name} was signed out of all devices ({$count} active).");
    }

    public function revokeOtherSessions(Request $request): RedirectResponse
    {
        $count = SessionRevoker::revokeAllExcept($request->session()->getId(), $request->user());

        LoginEvent::record('sessions_revoked', [
            'user_id' => null,
            'actor_id' => auth()->id(),
            'reason' => 'everyone_else',
        ]);

        return back()->with('status', "Signed out {$count} other ".\Illuminate\Support\Str::plural('device', $count).'.');
    }
}
