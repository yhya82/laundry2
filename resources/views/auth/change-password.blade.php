<x-guest-layout>
    {{-- Where EnforcePasswordPolicy sends a temporary or expired password. --}}
    <div class="mb-5">
        <h1 class="text-base font-semibold text-ink">Choose a new password</h1>
        <p class="text-sm text-ink-muted mt-1">
            @if ($reason === 'temporary')
                Your password was set by an administrator. Choose your own before you continue.
            @else
                Your password has expired. Choose a new one to continue.
            @endif
        </p>
    </div>

    <form method="POST" action="{{ route('password.change.update') }}" class="space-y-4" novalidate>
        @csrf
        @method('PUT')

        <div>
            <x-input-label for="current_password" :value="$reason === 'temporary' ? 'Temporary password' : 'Current password'" />
            <x-password-input id="current_password" name="current_password" required autocomplete="current-password" autofocus />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="New password" />
            <x-password-input id="password" name="password" required autocomplete="new-password" :requirements="true" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirm new password" />
            <x-password-input id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">Save new password</x-primary-button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm text-ink-muted hover:text-accent-ink underline underline-offset-2">Log out</button>
    </form>
</x-guest-layout>
