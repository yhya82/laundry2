<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        if (empty($validated['roles']) && $user->id === auth()->id()) {
            return back()->withErrors(['roles' => 'You cannot remove your own last role.']);
        }

        $user->syncRoles($validated['roles'] ?? []);

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

        $role->syncPermissions($permissions);

        return back()->with('status', "Permissions updated for {$role->name}.");
    }
}
