<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\NotRecentlyUsedPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * "Choose a new password" -- where EnforcePasswordPolicy sends a user whose
 * password is temporary (new account / Admin reset) or expired. Also usable
 * any time, but normally reached only through that redirect.
 */
class PasswordChangeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->needsPasswordChange()) {
            return redirect()->route('dashboard');
        }

        return view('auth.change-password', [
            'reason' => $user->must_change_password ? 'temporary' : 'expired',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults(), new NotRecentlyUsedPassword($user)],
        ], [
            'current_password.required' => 'Enter your current password.',
            'current_password.current_password' => 'That isn\'t your current password.',
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        return redirect()->route('dashboard')->with('status', 'Password updated.');
    }
}
