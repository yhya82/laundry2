<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\ActivityLog;
use App\Models\LoginEvent;
use App\Models\User;
use App\Rules\NotRecentlyUsedPassword;
use App\Rules\UniquePhone;
use App\Support\PhoneNumber;
use App\Support\SessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::with('roles')->orderBy('name')->paginate(15);
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissionsByModule = Permission::orderBy('name')->get()->groupBy('module');
        // Password changes are Admin-only, even if users.manage is delegated.
        $isAdmin = auth()->user()->hasRole('Admin');

        return view('users.index', compact('users', 'roles', 'permissionsByModule', 'isAdmin'));
    }

    public function show(User $user): View
    {
        $user->load('roles');
        $roles = Role::orderBy('name')->get();
        $recentActivity = ActivityLog::where('causer_id', $user->id)->latest('created_at')->limit(10)->get();

        return view('users.show', compact('user', 'roles', 'recentActivity'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->merge(['phone' => PhoneNumber::normalize($request->input('phone'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::VALID_PATTERN, new UniquePhone('users', $user->id, 'A staff member with this phone number already exists.')],
        ], [
            'phone.regex' => PhoneNumber::INVALID_MESSAGE,
        ]);

        $user->update($validated);

        return back()->with('status', 'User details updated.');
    }

    /**
     * Admin-only (the route also requires the Admin role, not just
     * users.manage -- so even if users.manage is delegated to another role,
     * only Admins can change passwords). Used from the Users list and the
     * user's own page.
     *
     * The new password is TEMPORARY: the user must choose their own at next
     * login. All their devices are signed out (including "Remember me"), and
     * the change is logged in the Security Center. An Admin changes their
     * OWN password on Profile, which asks for the current one.
     */
    public function setPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole('Admin'), 403, 'Only an Admin can change passwords.');

        // Their OWN password, from their own row: same safeguards as
        // Profile -- the current password is required (so an unattended
        // screen can't be used to take the account), and the new password
        // is theirs, not temporary. This device stays signed in.
        if ($user->id === auth()->id()) {
            $validated = $request->validateWithBag("setPassword{$user->id}", [
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

            return back()->with('status', 'Your password was changed.');
        }

        $validated = $request->validateWithBag("setPassword{$user->id}", [
            'password' => ['required', 'confirmed', Password::defaults(), new NotRecentlyUsedPassword($user)],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => true,
        ])->save();

        SessionRevoker::revokeUser($user);

        LoginEvent::record('password_reset_by_admin', [
            'user_id' => $user->id,
            'email' => $user->email,
            'actor_id' => auth()->id(),
        ]);

        return back()->with('status', "Password changed for {$user->name}. They'll be asked to choose a new one at next login.");
    }

    /**
     * Deactivated (never deleted) -- see EnsureUserIsActive for why: every
     * order, payment, and audit row this user ever touched still needs a
     * real name attached to it, not a dangling reference.
     */
    public function toggleActive(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot deactivate your own account.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', $user->is_active ? 'User activated.' : 'User deactivated.');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
        ]);

        // The Admin typed this password, so it's temporary: the new user
        // chooses their own at first login (EnforcePasswordPolicy).
        $user->forceFill(['must_change_password' => true])->saveQuietly();

        $user->syncRoles($validated['roles']);

        return back()->with('status', 'User created.');
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
        ]);

        Role::create(['name' => $validated['name']]);

        return back()->with('status', 'Role created — set its permissions below.');
    }

    /**
     * 'Admin' is hardcoded by name throughout this app's own guards
     * (updateRole/updateRolePermissions above, the Admin-always-keeps-
     * users.manage rule, SecurityRegressionTest) -- renaming it would
     * silently break every one of those checks instead of raising an error,
     * so it's blocked here rather than left to go wrong elsewhere.
     */
    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        if ($role->name === 'Admin') {
            return back()->withErrors(['name' => 'The Admin role cannot be renamed.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
        ]);

        $role->update(['name' => $validated['name']]);

        return back()->with('status', 'Role renamed.');
    }

    public function destroyRole(Role $role): RedirectResponse
    {
        if ($role->name === 'Admin') {
            return back()->withErrors(['role' => 'The Admin role cannot be deleted.']);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'This role is still assigned to one or more users — reassign them first.']);
        }

        $role->delete();

        return back()->with('status', 'Role deleted.');
    }

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $targetRoles = $validated['roles'] ?? [];

        if (empty($targetRoles) && $user->id === auth()->id()) {
            return back()->withErrors(['roles' => 'You cannot remove your own last role.']);
        }

        // Same no-op-today, real-later guard as setPassword() above -- a
        // users.manage holder on a lesser role shouldn't be able to grant
        // (to themselves or anyone) a role that carries users.manage.
        $grantsUserManagement = Role::whereIn('name', $targetRoles)
            ->whereHas('permissions', fn ($q) => $q->where('name', 'users.manage'))
            ->exists();

        if ($grantsUserManagement && ! auth()->user()->hasRole('Admin')) {
            return back()->withErrors(['roles' => 'Only an Admin can grant a role with user-management access.']);
        }

        $user->syncRoles($targetRoles);

        return back()->with('status', "Roles updated for {$user->name}.");
    }

    /**
     * users.manage is guarded on the Admin role specifically -- removing it
     * would lock every administrator out of this screen with no way back in
     * short of a database edit.
     */
    public function updateRolePermissions(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $permissions = $validated['permissions'] ?? [];

        if ($role->name === 'Admin' && ! in_array('users.manage', $permissions, true)) {
            return back()->withErrors(['permissions' => 'The Admin role must always keep users.manage, or no one could restore it.']);
        }

        // Same guard as updateRoles() -- granting users.manage to a role is
        // handing that role full admin-equivalent power, so it's reserved to
        // Admins themselves rather than anyone who merely has users.manage.
        if ($role->name !== 'Admin' && in_array('users.manage', $permissions, true) && ! auth()->user()->hasRole('Admin')) {
            return back()->withErrors(['permissions' => 'Only an Admin can grant user-management access to a role.']);
        }

        $role->syncPermissions($permissions);

        return back()->with('status', "Permissions updated for {$role->name}.");
    }
}
