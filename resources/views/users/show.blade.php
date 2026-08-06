<x-app-layout>
    <x-slot name="header">{{ $user->name }}</x-slot>

    <x-breadcrumbs :items="[
        ['label' => 'Users & Roles', 'url' => route('users.index')],
        ['label' => $user->name, 'url' => null],
    ]" />

    <div class="bg-surface border border-line rounded-2xl p-6 mb-5 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-accent text-white flex items-center justify-center text-xl font-bold flex-none">
                    {{ Str::substr($user->name, 0, 1) }}
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-lg font-bold text-ink">{{ $user->name }}</h2>
                        <span class="font-mono text-xs font-semibold px-2.5 py-1 rounded-full {{ $user->is_active ? 'bg-success-soft text-success' : 'bg-pill-bg text-pill-ink' }}">
                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="text-sm text-ink-muted mt-1">{{ $user->email }}</div>
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        @forelse ($user->roles as $role)
                            <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded-full bg-accent-soft text-accent-ink">{{ $role->name }}</span>
                        @empty
                            <span class="text-xs text-ink-faint">No roles assigned.</span>
                        @endforelse
                    </div>
                </div>
            </div>

            @if ($user->id !== auth()->id())
                <form method="POST" action="{{ route('users.toggleActive', $user) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 {{ $user->is_active ? 'bg-critical-soft text-critical' : 'bg-success-soft text-success' }} text-sm font-semibold px-4 py-2 rounded-lg hover:opacity-90 transition-opacity">
                        {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </form>
            @endif
        </div>
        @error('user') <p class="text-critical text-sm mt-3">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-surface border border-line rounded-2xl p-6">
            <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold mb-3">Details</div>
            <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <x-input-label for="user_name" value="Full name" />
                    <x-text-input id="user_name" name="name" type="text" class="block w-full" value="{{ old('name', $user->name) }}" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="user_email" value="Email" />
                    <x-text-input id="user_email" name="email" type="email" class="block w-full" value="{{ old('email', $user->email) }}" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                </div>
                <x-primary-button>Save details</x-primary-button>
            </form>
        </div>

        <div class="bg-surface border border-line rounded-2xl p-6">
            <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold mb-3">Set Password</div>
            <form method="POST" action="{{ route('users.setPassword', $user) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <x-input-label for="user_password" value="New password" />
                    <x-text-input id="user_password" name="password" type="password" class="block w-full" required />
                    <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="user_password_confirmation" value="Confirm password" />
                    <x-text-input id="user_password_confirmation" name="password_confirmation" type="password" class="block w-full" required />
                </div>
                <p class="text-xs text-ink-faint">{{ $user->name }} will need to use this new password on their next login.</p>
                <x-primary-button>Set password</x-primary-button>
            </form>
        </div>

        <div class="bg-surface border border-line rounded-2xl p-6">
            <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold mb-3">Roles</div>
            <form method="POST" action="{{ route('users.roles.update', $user) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="flex flex-wrap gap-3">
                    @foreach ($roles as $role)
                        <label class="inline-flex items-center gap-1.5 text-sm text-ink">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="rounded border-line-strong text-accent focus:ring-accent" @checked($user->roles->contains('name', $role->name))>
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('roles')" class="mt-1.5" />
                <x-primary-button>Save roles</x-primary-button>
            </form>
        </div>

        <div class="bg-surface border border-line rounded-2xl p-6">
            <div class="flex items-center justify-between mb-3">
                <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Recent Activity</div>
                <a href="{{ route('audit.index', ['causer_id' => $user->id]) }}" class="text-xs text-accent-ink hover:underline">View all</a>
            </div>
            @forelse ($recentActivity as $log)
                <div class="flex items-center justify-between py-2 border-b border-line last:border-0 text-sm">
                    <div>
                        <span class="text-ink">{{ ucfirst($log->description) }}</span>
                        <span class="font-mono text-xs text-ink-faint ml-1">{{ $log->subjectLabel() }}</span>
                    </div>
                    <span class="font-mono text-xs text-ink-faint">{{ $log->created_at->format('Y-m-d H:i') }}</span>
                </div>
            @empty
                <p class="text-ink-faint text-sm">No recorded activity yet.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
