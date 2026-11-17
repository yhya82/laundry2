@props(['requirements' => false])

{{--
    A password field with a show/hide toggle, used by every password box in
    the app. Pass :requirements="true" on a NEW-password field to list the
    current Security policy under it, checked LIVE as the person types (✓ met,
    ✗ not yet). Rules only the server can check (breach list, password
    history) say so. The field also dispatches `password-validity` with
    { ok } so a form can keep its submit button disabled until the policy is
    met -- the server re-checks everything on save regardless.
    Any other attributes (id, name, autocomplete, required, x-model, ...) go
    on the input.
--}}
@php
    $rules = $requirements ? \App\Support\SecurityPolicy::clientRules() : [];
@endphp
<div x-data="{
    visible: false,
    value: '',
    rules: @js($rules),
    // Same character classes as Laravel's Password rule.
    pass(rule) {
        const v = this.value;
        switch (rule.check) {
            // Count characters, not UTF-16 units -- matches PHP's mb_strlen,
            // so an emoji counts once here too.
            case 'min': return [...v].length >= rule.min;
            case 'mixed': return /\p{Lu}/u.test(v) && /\p{Ll}/u.test(v);
            case 'number': return /\p{N}/u.test(v);
            case 'symbol': return /[\p{Z}\p{S}\p{P}]/u.test(v);
            default: return null;
        }
    },
    get ok() {
        return this.rules.every((rule) => rule.check === 'server' || this.pass(rule));
    },
}"
    @if ($rules) x-init="$nextTick(() => $dispatch('password-validity', { ok: ok })); $watch('ok', (ok) => $dispatch('password-validity', { ok }))" @endif
>
    <div class="relative">
        <input
            :type="visible ? 'text' : 'password'"
            type="password"
            x-on:input="value = $event.target.value"
            {{ $attributes->merge(['class' => 'block w-full pr-10 bg-surface border-line-strong text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent rounded-lg shadow-sm text-sm']) }}
        >
        <button
            type="button"
            @click="visible = ! visible"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-ink-faint hover:text-ink focus:outline-none focus-visible:text-accent-ink"
            :aria-label="visible ? 'Hide password' : 'Show password'"
            :aria-pressed="visible.toString()"
            aria-label="Show password"
        >
            <svg x-show="! visible" xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1.01 1.01 0 0 1 0-.64C3.42 7.51 7.36 4.5 12 4.5c4.64 0 8.57 3.01 9.96 7.18.07.21.07.43 0 .64C20.58 16.49 16.64 19.5 12 19.5c-4.64 0-8.57-3.01-9.96-7.18Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            <svg x-show="visible" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.48 10.48 0 0 0 2.04 12c1.39 4.17 5.32 7.5 9.96 7.5 1.59 0 3.1-.36 4.44-1M6.23 6.23A10.45 10.45 0 0 1 12 4.5c4.64 0 8.57 3.01 9.96 7.18a10.5 10.5 0 0 1-4.29 5.46M6.23 6.23 3 3m3.23 3.23 3.65 3.65m7.89 7.89L21 21m-3.23-3.23-3.65-3.65m0 0a3 3 0 1 0-4.24-4.24m4.24 4.24L9.88 9.88" />
            </svg>
        </button>
    </div>

    @if ($rules)
        <ul class="mt-2 space-y-1 text-xs" aria-live="polite">
            @foreach ($rules as $i => $rule)
                <li class="flex items-center gap-1.5 transition-colors"
                    @if ($rule['check'] !== 'server')
                        :class="value === '' ? 'text-ink-faint' : (pass(rules[{{ $i }}]) ? 'text-emerald-600' : 'text-critical')"
                    @endif
                    class="text-ink-faint">
                    @if ($rule['check'] === 'server')
                        {{-- Only the server can check this one. --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        <span>{{ $rule['text'] }} <span class="text-ink-faint">(checked when you save)</span></span>
                    @else
                        <span class="flex-none w-3.5 h-3.5 inline-flex items-center justify-center" aria-hidden="true">
                            <svg x-show="value !== '' && pass(rules[{{ $i }}])" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            <svg x-show="value !== '' && ! pass(rules[{{ $i }}])" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            <span x-show="value === ''" class="w-1 h-1 rounded-full bg-current"></span>
                        </span>
                        <span>{{ $rule['text'] }}</span>
                        <span class="sr-only" x-text="value === '' ? '' : (pass(rules[{{ $i }}]) ? '(met)' : '(not met yet)')"></span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
