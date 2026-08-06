<x-app-layout>
    <x-slot name="header">Users &amp; Roles</x-slot>

    <div class="flex items-center justify-between mb-5">
        <h2 class="font-semibold text-ink">Staff Users</h2>
        <x-panel-trigger panel="user-create">+ New User</x-panel-trigger>
    </div>

    <div class="bg-surface border border-line rounded-2xl overflow-hidden hidden md:block mb-8">
        <table class="w-full text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Name</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Email</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Roles</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="border-t border-line hover:bg-surface-2">
                        <td class="px-4 py-3">
                            <a href="{{ route('users.show', $user) }}" class="text-ink font-medium hover:text-accent-ink hover:underline">{{ $user->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-ink-muted">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1.5">
                                @forelse ($user->roles as $role)
                                    <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded-full bg-accent-soft text-accent-ink">{{ $role->name }}</span>
                                @empty
                                    <span class="text-xs text-ink-faint">None</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded-full {{ $user->is_active ? 'bg-success-soft text-success' : 'bg-pill-bg text-pill-ink' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-ink-faint text-sm">No users yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="md:hidden space-y-3 mb-8">
        @forelse ($users as $user)
            <a href="{{ route('users.show', $user) }}" class="block bg-surface border border-line rounded-2xl p-4">
                <div class="flex items-center justify-between mb-1">
                    <span class="font-medium text-ink">{{ $user->name }}</span>
                    <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded-full {{ $user->is_active ? 'bg-success-soft text-success' : 'bg-pill-bg text-pill-ink' }}">
                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="text-ink-muted text-sm mb-2">{{ $user->email }}</div>
                <div class="flex flex-wrap gap-1.5">
                    @forelse ($user->roles as $role)
                        <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded-full bg-accent-soft text-accent-ink">{{ $role->name }}</span>
                    @empty
                        <span class="text-xs text-ink-faint">No roles</span>
                    @endforelse
                </div>
            </a>
        @empty
            <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No users yet.</div>
        @endforelse
    </div>

    <div class="mb-8">{{ $users->links() }}</div>

    <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-ink">Permission Matrix</h2>
        <x-panel-trigger panel="role-create">+ New Role</x-panel-trigger>
    </div>
    <div class="bg-surface border border-line rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-2">
                    <tr>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Permission</th>
                        @foreach ($roles as $role)
                            <th class="text-center font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3 whitespace-nowrap">
                                {{ $role->name }}
                                <button type="submit" form="role-{{ $role->id }}-permissions" class="block mx-auto mt-1 text-accent-ink text-xs font-semibold normal-case tracking-normal hover:underline">Save</button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($permissionsByModule as $module => $permissions)
                        <tr class="border-t border-line bg-surface-2">
                            <td colspan="{{ 1 + $roles->count() }}" class="px-4 py-2 font-mono text-xs uppercase tracking-wide text-ink-faint">{{ $module }}</td>
                        </tr>
                        @foreach ($permissions as $permission)
                            <tr class="border-t border-line">
                                <td class="px-4 py-2.5 text-ink">{{ $permission->name }}</td>
                                @foreach ($roles as $role)
                                    <td class="px-4 py-2.5 text-center">
                                        <input
                                            type="checkbox"
                                            name="permissions[]"
                                            value="{{ $permission->name }}"
                                            form="role-{{ $role->id }}-permissions"
                                            class="rounded border-line-strong text-accent focus:ring-accent"
                                            @checked($role->permissions->contains('name', $permission->name))
                                        >
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($roles as $role)
        <form id="role-{{ $role->id }}-permissions" method="POST" action="{{ route('roles.permissions.update', $role) }}">
            @csrf
            @method('PUT')
        </form>
    @endforeach

    <x-slide-panel name="user-create" title="New User" :error-fields="['name', 'email', 'password', 'roles']">
        <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
            @csrf
            <div>
                <x-input-label for="name" value="Full name" />
                <x-text-input id="name" name="name" type="text" class="block w-full" value="{{ old('name') }}" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input id="email" name="email" type="email" class="block w-full" value="{{ old('email') }}" autocomplete="off" required />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label for="password" value="Password" />
                <x-text-input id="password" name="password" type="password" class="block w-full" autocomplete="new-password" required />
                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label for="password_confirmation" value="Confirm password" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="block w-full" autocomplete="new-password" required />
            </div>
            <div>
                <x-input-label value="Roles" />
                <div class="flex flex-wrap gap-3 mt-1">
                    @foreach ($roles as $role)
                        <label class="inline-flex items-center gap-1.5 text-sm text-ink">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="rounded border-line-strong text-accent focus:ring-accent" @checked(in_array($role->name, old('roles', [])))>
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('roles')" class="mt-1.5" />
            </div>
            <div class="flex items-center gap-3">
                <x-primary-button>Create user</x-primary-button>
                <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
            </div>
        </form>
    </x-slide-panel>

    <x-slide-panel name="role-create" title="New Role" :error-fields="['name']">
        <form method="POST" action="{{ route('roles.store') }}" class="space-y-4">
            @csrf
            <div>
                <x-input-label for="role_name" value="Role name" />
                <x-text-input id="role_name" name="name" type="text" class="block w-full" value="{{ old('name') }}" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
            </div>
            <p class="text-xs text-ink-faint">Starts with no permissions — set them in the Permission Matrix once created.</p>
            <div class="flex items-center gap-3">
                <x-primary-button>Create role</x-primary-button>
                <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
            </div>
        </form>
    </x-slide-panel>
</x-app-layout>
