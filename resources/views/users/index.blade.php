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
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Phone</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Status</th>
                    @if ($isAdmin)
                        <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    @endif
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
                        <td class="px-4 py-3 text-ink-muted">{{ str_replace('+220', '+220 ', $user->phone) }}</td>
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded-full {{ $user->is_active ? 'bg-success-soft text-success' : 'bg-pill-bg text-pill-ink' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        @if ($isAdmin)
                            <td class="px-4 py-3 text-right">
                                {{-- Every row, the Admin's own included (that one asks for the current password). --}}
                                {{-- Same label and fixed width on every row; the panel itself says "(you)" on your own. --}}
                                <button type="button" @click="$dispatch('open-panel', 'user-password-{{ $user->id }}')" class="inline-flex w-40 items-center justify-center gap-1.5 rounded-lg border border-line-strong bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink-muted hover:text-ink hover:border-accent transition-colors" title="{{ $user->id === auth()->id() ? 'Change your own password' : 'Change password for '.$user->name }}" aria-label="{{ $user->id === auth()->id() ? 'Change your own password' : 'Change password for '.$user->name }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.03 5.91c-.56-.1-1.16.02-1.56.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.82c0-.6.24-1.17.66-1.59l6.41-6.41c.41-.4.53-1 .43-1.56A6 6 0 1 1 21.75 8.25Z" /></svg>
                                    Change password
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-ink-faint text-sm">No users yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="md:hidden space-y-3 mb-8">
        @forelse ($users as $user)
            <div class="bg-surface border border-line rounded-2xl">
            <a href="{{ route('users.show', $user) }}" class="block p-4">
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
            @if ($isAdmin)
                <div class="border-t border-line px-4 py-2.5">
                    <button type="button" @click="$dispatch('open-panel', 'user-password-{{ $user->id }}')" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-muted hover:text-accent-ink">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.03 5.91c-.56-.1-1.16.02-1.56.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.82c0-.6.24-1.17.66-1.59l6.41-6.41c.41-.4.53-1 .43-1.56A6 6 0 1 1 21.75 8.25Z" /></svg>
                        Change password
                    </button>
                </div>
            @endif
            </div>
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
                                <button type="submit" form="role-{{ $role->id }}-permissions" class="block mx-auto mt-1 px-3 py-1 rounded-md bg-success text-white text-xs font-semibold normal-case tracking-normal hover:bg-success-soft hover:text-success transition-colors">Save</button>
                                @if ($role->name !== 'Admin')
                                    <div class="flex items-center justify-center gap-2 mt-1.5">
                                        <button type="button" @click="$dispatch('open-panel', 'role-rename-{{ $role->id }}')" class="w-6 h-6 rounded-md bg-accent text-white flex items-center justify-center hover:bg-accent-soft hover:text-accent-ink transition-colors" title="Rename role" aria-label="Rename role">
                                            <x-nav-icon name="edit" class="w-3 h-3" />
                                        </button>
                                        <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Delete the {{ $role->name }} role? This cannot be undone.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="w-6 h-6 rounded-md bg-critical text-white flex items-center justify-center hover:bg-critical-soft hover:text-critical transition-colors" title="Delete role" aria-label="Delete role">
                                                <x-nav-icon name="trash" class="w-3 h-3" />
                                            </button>
                                        </form>
                                    </div>
                                @endif
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

    {{--
        :open is set explicitly off old('role_id') rather than :error-fields,
        since every role's rename form shares the field name "name" -- an
        error-fields check (as every other slide-panel on this page uses)
        would auto-open every role's panel at once on a single validation
        error, not just the one that was actually submitted.
    --}}
    @foreach ($roles as $role)
        @if ($role->name !== 'Admin')
            <x-slide-panel name="role-rename-{{ $role->id }}" title="Rename Role" :open="old('role_id') == $role->id && $errors->any()">
                <form method="POST" action="{{ route('roles.update', $role) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="role_id" value="{{ $role->id }}">
                    <div>
                        <x-input-label for="role_rename_{{ $role->id }}" value="Role name" />
                        <x-text-input id="role_rename_{{ $role->id }}" name="name" type="text" class="block w-full" value="{{ old('role_id') == $role->id ? old('name') : $role->name }}" required autofocus />
                        <x-input-error :messages="old('role_id') == $role->id ? $errors->get('name') : []" class="mt-1.5" />
                    </div>
                    <div class="flex items-center gap-3">
                        <x-primary-button>Save</x-primary-button>
                        <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                    </div>
                </form>
            </x-slide-panel>
        @endif
    @endforeach

    {{-- One "Change password" panel per user (Admin only). The Admin's own
         asks for the current password, and the new one isn't temporary. --}}
    @if ($isAdmin)
        @foreach ($users as $user)
            @php
                $bag = $errors->getBag("setPassword{$user->id}");
                $isSelf = $user->id === auth()->id();
            @endphp
            <x-slide-panel name="user-password-{{ $user->id }}" :title="$isSelf ? 'Change my password' : 'Change password'" :open="$bag->any()">
                {{-- The policy is checked live: Change password stays disabled until
                     every rule the browser can check passes and both entries match.
                     UserController::setPassword() re-checks everything on save. --}}
                <form method="POST" action="{{ route('users.setPassword', $user) }}" class="space-y-4" novalidate
                    x-data="{ current: '', pw: '', confirm: '', policyOk: false, needsCurrent: @js($isSelf) }"
                    x-on:password-validity="policyOk = $event.detail.ok">
                    @csrf
                    @method('PUT')
                    <div class="rounded-xl bg-surface-2 px-3 py-2.5">
                        <div class="text-sm font-medium text-ink">{{ $user->name }}{{ $isSelf ? ' (you)' : '' }}</div>
                        <div class="text-xs text-ink-faint">{{ $user->email }}</div>
                    </div>
                    @if ($isSelf)
                        <div>
                            <x-input-label for="pw_{{ $user->id }}_current" value="Current password" />
                            <x-password-input id="pw_{{ $user->id }}_current" name="current_password" autocomplete="current-password" required x-model="current" />
                            <x-input-error :messages="$bag->get('current_password')" class="mt-1.5" />
                        </div>
                    @endif
                    <div>
                        <x-input-label for="pw_{{ $user->id }}" value="New password" />
                        <x-password-input id="pw_{{ $user->id }}" name="password" autocomplete="new-password" required :requirements="true" x-model="pw" />
                        <x-input-error :messages="$bag->get('password')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="pw_{{ $user->id }}_confirmation" value="Confirm password" />
                        <x-password-input id="pw_{{ $user->id }}_confirmation" name="password_confirmation" autocomplete="new-password" required x-model="confirm" />
                        <p x-show="confirm !== '' && confirm !== pw" x-cloak class="mt-1.5 text-xs text-critical">The passwords don't match yet.</p>
                        <p x-show="confirm !== '' && confirm === pw" x-cloak class="mt-1.5 text-xs text-emerald-600">Passwords match.</p>
                    </div>
                    <ul class="rounded-xl border border-line px-3 py-2.5 text-xs text-ink-muted space-y-1">
                        @if ($isSelf)
                            <li>• This becomes your password straight away. You stay signed in here.</li>
                        @else
                            <li>• This password is temporary: they'll choose their own at next login.</li>
                            <li>• They'll be signed out of every device right away.</li>
                        @endif
                    </ul>
                    <div class="flex items-center gap-3">
                        <x-primary-button
                            x-bind:disabled="! (policyOk && pw !== '' && pw === confirm && (! needsCurrent || current !== ''))"
                            class="disabled:opacity-50 disabled:cursor-not-allowed">Change password</x-primary-button>
                        <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                    </div>
                    <p x-show="! (policyOk && pw !== '' && pw === confirm)" class="text-xs text-ink-faint">Meet the password policy above and confirm the password to continue.</p>
                </form>
            </x-slide-panel>
        @endforeach
    @endif

    <x-slide-panel name="user-create" title="New User" :error-fields="['name', 'email', 'phone', 'password', 'roles']">
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
                <x-input-label for="phone" value="Phone" />
                <x-text-input id="phone" name="phone" type="text" class="block w-full" value="{{ old('phone') }}" placeholder="555123456" required />
                <x-input-error :messages="$errors->get('phone')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label for="password" value="Temporary password" />
                <x-password-input id="password" name="password" autocomplete="new-password" required :requirements="true" />
                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label for="password_confirmation" value="Confirm password" />
                <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" required />
            </div>
            <p class="text-xs text-ink-faint">They'll be asked to choose their own password at first login.</p>
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
