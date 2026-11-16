<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    {{-- Why the user is here, if they were signed out (ExplainSignOut). --}}
    @if (session('signed_out_reason') === 'admin')
        <div class="mb-4 rounded-xl border border-line-strong bg-surface-2 p-3" role="status">
            <p class="text-sm font-semibold text-ink">Your session was ended by an administrator.</p>
            <p class="text-xs text-ink-muted mt-0.5">Log in again to continue.</p>
        </div>
    @elseif (session('signed_out_reason') === 'idle')
        <div class="mb-4 rounded-xl border border-line-strong bg-surface-2 p-3" role="status">
            <p class="text-sm font-semibold text-ink">You were signed out after {{ session('signed_out_minutes') }} minutes of inactivity.</p>
            <p class="text-xs text-ink-muted mt-0.5">Log in again to continue.</p>
        </div>
    @endif

    {{-- Login problems (wrong details, locked, deactivated) as one clear alert. --}}
    @if ($errors->has('login'))
        <div class="mb-4 flex gap-2.5 rounded-xl border border-critical bg-critical-soft p-3" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-none text-critical mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <div>
                <p class="text-sm font-semibold text-critical">{{ $errors->first('login') }}</p>
                @if ($errors->has('login_hint'))
                    <p class="text-xs text-ink-muted mt-0.5">{{ $errors->first('login_hint') }}</p>
                @endif
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4" novalidate>
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block w-full {{ $errors->has('email') ? 'border-critical' : '' }}" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" aria-describedby="email-error" />
            <x-input-error id="email-error" :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-password-input id="password" name="password" required autocomplete="current-password" aria-describedby="password-error" class="{{ $errors->has('password') ? 'border-critical' : '' }}" />
            <x-input-error id="password-error" :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="flex items-center gap-2 text-sm text-ink-muted">
            <input id="remember_me" type="checkbox" class="rounded border-line-strong text-accent shadow-sm focus:ring-accent" name="remember">
            {{ __('Remember me') }}
        </label>

        <div class="pt-2 flex flex-col items-center gap-3">
            <x-primary-button class="w-full">
                {{ __('Log in') }}
            </x-primary-button>

            @if (Route::has('password.request'))
                <a class="text-sm text-ink-muted hover:text-accent-ink underline underline-offset-2" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>
    </form>
</x-guest-layout>
