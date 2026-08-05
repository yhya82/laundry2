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
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 text-ink font-medium">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-ink-muted">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('users.roles.update', $user) }}" class="flex flex-wrap items-center gap-3">
                                @csrf
                                @method('PUT')
                                @foreach ($roles as $role)
                                    <label class="inline-flex items-center gap-1.5 text-xs text-ink-muted">
                                        <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="rounded border-line-strong text-accent focus:ring-accent" @checked($user->roles->contains('name', $role->name))>
                                        {{ $role->name }}
                                    </label>
                                @endforeach
                                <button type="submit" class="text-accent-ink text-xs font-semibold hover:underline">Save</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-10 text-center text-ink-faint text-sm">No users yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="md:hidden space-y-3 mb-8">
        @forelse ($users as $user)
            <div class="bg-surface border border-line rounded-2xl p-4">
                <div class="font-medium text-ink">{{ $user->name }}</div>
                <div class="text-ink-muted text-sm mb-2">{{ $user->email }}</div>
                <form method="POST" action="{{ route('users.roles.update', $user) }}" class="flex flex-wrap items-center gap-3">
                    @csrf
                    @method('PUT')
                    @foreach ($roles as $role)
                        <label class="inline-flex items-center gap-1.5 text-xs text-ink-muted">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="rounded border-line-strong text-accent focus:ring-accent" @checked($user->roles->contains('name', $role->name))>
                            {{ $role->name }}
                        </label>
                    @endforeach
                    <button type="submit" class="text-accent-ink text-xs font-semibold hover:underline">Save</button>
                </form>
            </div>
        @empty
            <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No users yet.</div>
        @endforelse
    </div>

    <div class="mb-8">{{ $users->links() }}</div>

    <h2 class="font-semibold text-ink mb-3">Permission Matrix</h2>
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
                <x-text-input id="email" name="email" type="email" class="block w-full" value="{{ old('email') }}" required />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label for="password" value="Password" />
                <x-text-input id="password" name="password" type="password" class="block w-full" required />
                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label for="password_confirmation" value="Confirm password" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="block w-full" required />
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
</x-app-layout>
