<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\ActivityLog;
use App\Models\User;
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

        return view('users.index', compact('users', 'roles', 'permissionsByModule'));
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        return back()->with('status', 'User details updated.');
    }

    public function setPassword(Request $request, User $user): RedirectResponse
    {
        // users.manage is currently Admin-only, so this is a no-op today --
        // it's here so that if users.manage is ever delegated to a lesser
        // role, that role still can't take over an Admin account by resetting
        // its password.
        if ($user->hasRole('Admin') && ! auth()->user()->hasRole('Admin')) {
            return back()->withErrors(['user' => 'Only an Admin can reset another Admin\'s password.']);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => $validated['password']]);

        return back()->with('status', "Password updated for {$user->name}.");
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
            'password' => $validated['password'],
        ]);

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
