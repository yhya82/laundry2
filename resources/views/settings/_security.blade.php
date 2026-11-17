{{--
    Settings → Security (Admin only). Data from SettingsController::securityData().

    Layout: red header (Password Policies · Refresh · alerts bell) → Security
    Score card → tabs: Overview · Policy · Devices · Login activity · Threats.
    The open tab survives filters, pagination and a failed save via ?sec_tab=.
--}}
@use('App\Models\LoginEvent')
@use('App\Support\SecurityPolicy', 'Policy')
@use('App\Support\UserAgent')
@php
    $card = 'bg-surface border border-line rounded-2xl';
    $label = 'font-mono text-xs uppercase tracking-wide text-ink-faint';

    // Which tab opens: a failed policy save -> Policy; activity/device
    // filters -> their tab; otherwise ?sec_tab or Overview.
    $secTab = match (true) {
        old('group') === 'security' => 'policy',
        request()->hasAny(['ev_event', 'ev_email', 'ev_ip', 'ev_reason', 'ev_from', 'ev_to', 'events_page']) && ! request()->filled('sec_tab') => 'activity',
        request()->hasAny(['sess_user', 'sess_device', 'sess_recent']) && ! request()->filled('sec_tab') => 'devices',
        request()->hasAny(['people_status', 'people_q']) && ! request()->filled('sec_tab') => 'people',
        default => request('sec_tab', 'overview'),
    };
    $peopleIcon = 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z';
    $secUrl = fn (array $params = []) => route('settings.index', ['tab' => 'security', ...$params]);

    // Colours per tone (default Tailwind palette -- opacity works there,
    // unlike the theme's CSS-variable colours).
    $tones = [
        'good' => ['stroke' => '#10b981', 'text' => 'text-emerald-600', 'soft' => 'bg-emerald-500/10', 'icon' => 'text-emerald-500'],
        'fair' => ['stroke' => '#f59e0b', 'text' => 'text-amber-600', 'soft' => 'bg-amber-500/10', 'icon' => 'text-amber-500'],
        'poor' => ['stroke' => '#ef4444', 'text' => 'text-red-600', 'soft' => 'bg-red-500/10', 'icon' => 'text-red-500'],
    ];
    $tone = $tones[$score['tone']];
    $circumference = 2 * M_PI * 42;

    $icons = [
        'shield' => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
        'lock' => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
        'refresh' => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99',
        'bell' => 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
        'overview' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z',
        'devices' => 'M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25',
        'activity' => 'M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
        'threats' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
        'user' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
        'clock' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'key' => 'M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.03 5.91c-.56-.1-1.16.02-1.56.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.82c0-.6.24-1.17.66-1.59l6.41-6.41c.41-.4.53-1 .43-1.56A6 6 0 1 1 21.75 8.25Z',
        'bulb' => 'M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18',
    ];
    $deviceIcons = [
        'Desktop' => $icons['devices'],
        'Phone' => 'M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3',
        'Tablet' => 'M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-15a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z',
        'Unknown' => 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z',
    ];
    $tabs = [
        'overview' => ['Overview', $icons['overview'], null],
        'policy' => ['Policy', $icons['lock'], null],
        // Badge: how many people are signed in right now.
        'people' => ['People', $peopleIcon, ($peopleCounts['online'] + $peopleCounts['idle']) ?: null],
        'devices' => ['Devices', $icons['devices'], $overview['devices']],
        'activity' => ['Login activity', $icons['activity'], $overview['failed_today'] ?: null],
        'threats' => ['Threats', $icons['threats'], ($topIps->count() ?: null)],
    ];
    $eventTabs = ['failed' => 'Failed attempts', 'all' => 'All activity', 'login' => 'Sign-ins', 'lockout' => 'Lockouts', 'password_reset_by_admin' => 'Password changes', 'sessions_revoked' => 'Sign-outs by admin'];
    $eventPill = [
        'login' => 'bg-success-soft text-success',
        'logout' => 'bg-pill-bg text-pill-ink',
        'failed' => 'bg-critical-soft text-critical',
        'lockout' => 'bg-critical text-white',
        'password_reset_by_admin' => 'bg-accent-soft text-accent-ink',
        'sessions_revoked' => 'bg-accent-soft text-accent-ink',
    ];
    $bellDot = ['ok' => 'bg-emerald-400', 'warning' => 'bg-amber-400', 'critical' => 'bg-red-500 animate-pulse'][$alertLevel];
    $bellTitle = match ($alertLevel) {
        'critical' => $overview['locked'].' account(s) locked right now',
        'warning' => $failedLast24h.' failed login(s) in the last 24 hours',
        default => 'No failed logins in the last 24 hours',
    };
    // Password Policies panel: one policy, created from a preset or edited.
    $policySaved = Policy::isSaved();
    $policyErrors = old('group') === 'security';
    $currentPolicy = Policy::currentValues();
    $formValues = $policyErrors
        ? collect($currentPolicy)->map(fn ($value, $key) => is_bool($value) ? (bool) old($key) : (int) old($key, $value))->all()
        : $currentPolicy;
    $presetIcons = ['basic' => $icons['user'], 'recommended' => $icons['shield'], 'strict' => $icons['lock']];
    $presetTones = ['basic' => 'bg-sky-500/10 text-sky-500', 'recommended' => 'bg-emerald-500/10 text-emerald-500', 'strict' => 'bg-red-500/10 text-red-500'];

    $svg = fn (string $path, string $class = 'w-5 h-5', string $width = '1.8') => '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="'.$width.'" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="'.$path.'" /></svg>';
@endphp

<div class="space-y-6" x-data="{
    secTab: @js($secTab),
    // Password Policies panel. mode: 'pick' a preset, or edit the 'form'.
    policyOpen: @js($policyErrors),
    creating: @js($policyErrors ? old('policy_mode') === 'create' : ! $policySaved),
    mode: 'form',
    chosenPreset: null,
    form: @js($formValues),
    current: @js($currentPolicy),
    presets: @js(Policy::PRESETS),
    openPolicy(create = false) {
        this.creating = create || ! @js($policySaved);
        this.mode = this.creating ? 'pick' : 'form';
        this.chosenPreset = null;
        if (! this.creating) this.form = { ...this.current };
        this.policyOpen = true;
    },
    usePreset(key) {
        this.form = { ...this.presets[key].values };
        this.chosenPreset = key;
        this.mode = 'form';
    },
    useCurrent() {
        this.form = { ...this.current };
        this.chosenPreset = null;
        this.mode = 'form';
    },
    // In-place navigation: links and GET filter forms that stay on this
    // Security page (sub-tabs, filters, Clear, pagination, IP/email links)
    // fetch the page in the background and swap ONLY the [data-sec-swap]
    // sections, instead of reloading everything. The URL is kept in sync so
    // Refresh and Back still work. Anything else navigates normally.
    swapping: false,
    async swap(url) {
        this.swapping = true;
        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok || res.redirected) { window.location = url; return; }
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            this.$root.querySelectorAll('[data-sec-swap]').forEach((el) => {
                const fresh = doc.getElementById(el.id);
                if (fresh) el.innerHTML = fresh.innerHTML;
            });
            const tab = new URL(url, window.location.href).searchParams.get('sec_tab');
            if (tab) this.secTab = tab;
            history.pushState({}, '', url);
        } catch (e) {
            window.location = url;
        } finally {
            this.swapping = false;
        }
    },
    isSecurityUrl(url) {
        const u = new URL(url, window.location.href);
        return u.origin === window.location.origin && u.pathname === window.location.pathname && u.searchParams.get('tab') === 'security';
    },
    interceptLink(event) {
        const a = event.target.closest('a[href]');
        if (! a || a.target || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
        if (! this.isSecurityUrl(a.href)) return;
        event.preventDefault();
        this.swap(a.href);
    },
    interceptForm(event) {
        const form = event.target;
        if ((form.method || 'get').toLowerCase() !== 'get') return;
        const params = new URLSearchParams(new FormData(form));
        // Drop empty filters so the URL stays tidy.
        [...params.keys()].forEach((k) => params.get(k) === '' && params.delete(k));
        const url = form.action.split('?')[0] + '?' + params.toString();
        if (! this.isSecurityUrl(url)) return;
        event.preventDefault();
        this.swap(url);
    },
}"
    x-on:keydown.escape.window="policyOpen = false"
    x-on:click="interceptLink($event)"
    x-on:submit="interceptForm($event)"
    x-on:popstate.window="isSecurityUrl(window.location.href) && window.location.reload()"
>

    {{-- Header --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-red-600 via-red-700 to-red-800 px-6 py-6 sm:px-8 shadow-lg">
        {{-- Soft decorative glow --}}
        <div class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>

        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <span class="flex h-14 w-14 flex-none items-center justify-center rounded-2xl bg-white text-red-600 shadow-md">{!! $svg($icons['shield'], 'w-7 h-7', '2') !!}</span>
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">Security Center</h2>
                    <p class="text-sm sm:text-base text-red-100">Security monitoring and management</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <button type="button" @click="openPolicy()" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-red-600 shadow-sm transition hover:bg-red-50 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70">
                    {!! $svg($icons['lock'], 'w-[18px] h-[18px]', '2') !!}
                    Password Policies
                </button>
                <button type="button" @click="window.location = @js($secUrl()) + '&sec_tab=' + secTab" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-red-600 shadow-sm transition hover:bg-red-50 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70">
                    {!! $svg($icons['refresh'], 'w-[18px] h-[18px]', '2') !!}
                    Refresh
                </button>
                <button type="button" @click="secTab = 'activity'; $nextTick(() => $refs.secTabs.scrollIntoView({ behavior: 'smooth', block: 'start' }))" class="relative inline-flex h-[42px] w-[42px] items-center justify-center rounded-xl bg-white text-red-600 shadow-sm transition hover:bg-red-50 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70" title="{{ $bellTitle }}" aria-label="Security alerts: {{ $bellTitle }}">
                    {!! $svg($icons['bell'], 'w-5 h-5', '2') !!}
                    <span class="absolute -right-1 -top-1 h-3 w-3 rounded-full ring-2 ring-white {{ $bellDot }}" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Security Score --}}
    <div class="{{ $card }} p-6 sm:p-8 shadow-sm">
        <div class="flex flex-col gap-8 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h3 class="text-xl sm:text-2xl font-bold text-ink">Security Score</h3>
                <p class="text-sm text-ink-muted mt-1">Overall system security health</p>

                <div class="mt-6 flex flex-col gap-6 sm:flex-row sm:items-center">
                    {{-- Ring gauge --}}
                    <div class="relative h-36 w-36 flex-none" role="img" aria-label="Security score {{ $score['score'] }} out of 100">
                        <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90" aria-hidden="true">
                            <circle cx="50" cy="50" r="42" fill="none" stroke-width="9" class="text-line" stroke="currentColor" />
                            <circle cx="50" cy="50" r="42" fill="none" stroke-width="9" stroke-linecap="round"
                                stroke="{{ $tone['stroke'] }}"
                                stroke-dasharray="{{ $circumference }}"
                                stroke-dashoffset="{{ $circumference * (1 - $score['score'] / 100) }}"
                                style="transition: stroke-dashoffset 800ms cubic-bezier(0.23, 1, 0.32, 1)" />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-4xl font-bold tabular-nums {{ $tone['text'] }}">{{ $score['score'] }}</span>
                            <span class="text-[11px] text-ink-faint">out of 100</span>
                        </div>
                    </div>

                    <dl class="flex-1 min-w-0 space-y-3 text-sm">
                        @foreach ([
                            ['Password Strength', $score['password_strength'], $tones[$score['password_tone']]['text']],
                            ['2FA Status', $score['two_factor'], 'text-ink-muted'],
                            ['Suspicious Activity', $score['suspicious'], $tones[$score['suspicious_tone']]['text']],
                        ] as [$name, $value, $class])
                            <div class="flex items-center justify-between gap-4 border-b border-line pb-2.5 last:border-0 last:pb-0">
                                <dt class="text-ink-muted">{{ $name }}</dt>
                                <dd class="font-semibold {{ $class }}">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>

            {{-- Lock illustration, coloured by the score --}}
            <div class="hidden md:flex h-48 w-48 lg:h-56 lg:w-56 flex-none items-center justify-center rounded-full {{ $tone['soft'] }}" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-24 w-24 lg:h-28 lg:w-28 {{ $tone['icon'] }} drop-shadow-sm" fill="currentColor">
                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" />
                    <circle cx="12" cy="15.4" r="1.6" fill="white" />
                    <rect x="11.25" y="15.6" width="1.5" height="3" rx="0.6" fill="white" />
                </svg>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div x-ref="secTabs" class="scroll-mt-4">
        <div class="flex gap-1 overflow-x-auto rounded-2xl border border-line bg-surface p-1.5 shadow-sm" role="tablist">
            @foreach ($tabs as $key => [$text, $path, $badge])
                <button type="button" role="tab" @click="secTab = '{{ $key }}'" :aria-selected="secTab === '{{ $key }}'"
                    class="inline-flex flex-none items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold whitespace-nowrap transition-colors"
                    :class="secTab === '{{ $key }}' ? 'bg-red-600 text-white shadow-sm' : 'text-ink-muted hover:bg-surface-2 hover:text-ink'">
                    {!! $svg($path, 'w-[18px] h-[18px]') !!}
                    {{ $text }}
                    @if ($badge)
                        <span class="rounded-full px-1.5 py-px font-mono text-[11px] font-bold"
                            :class="secTab === '{{ $key }}' ? 'bg-white/25 text-white' : 'bg-surface-2 text-ink-muted'">{{ $badge }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- ============ Overview ============ --}}
    <div x-show="secTab === 'overview'" x-cloak class="space-y-6">
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">
            {{-- Each card has its own icon colour; the number turns red/amber when it needs attention. --}}
            @foreach ([
                ['Failed logins today', $overview['failed_today'], $overview['failed_today'] > 0 ? 'poor' : null, $icons['threats'], 'activity', 'bg-red-500/10 text-red-500'],
                ['Locked right now', $overview['locked'], $overview['locked'] > 0 ? 'poor' : null, $icons['lock'], 'activity', 'bg-orange-500/10 text-orange-500'],
                ['Signed-in devices', $overview['devices'], null, $icons['devices'], 'devices', 'bg-sky-500/10 text-sky-500'],
                ['Temporary passwords', $overview['temporary'], null, $icons['key'], null, 'bg-violet-500/10 text-violet-500'],
                ['Expiring in 7 days', $overview['expiring_soon'], $overview['expiring_soon'] > 0 ? 'fair' : null, $icons['clock'], null, 'bg-amber-500/10 text-amber-500'],
                ['Expired passwords', $overview['expired'], $overview['expired'] > 0 ? 'poor' : null, $icons['user'], null, 'bg-pink-500/10 text-pink-500'],
            ] as [$title, $value, $alert, $path, $goTo, $iconTone])
                <{{ $goTo ? 'button type=button' : 'div' }} @if ($goTo) @click="secTab = '{{ $goTo }}'" @endif class="{{ $card }} p-4 text-left shadow-sm {{ $goTo ? 'transition hover:-translate-y-0.5 hover:shadow-md hover:border-line-strong' : '' }}">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $iconTone }}">{!! $svg($path, 'w-5 h-5', '2') !!}</span>
                    <div class="mt-3 text-2xl font-bold tabular-nums {{ $alert ? $tones[$alert]['text'] : 'text-ink' }}">{{ $value }}</div>
                    <div class="text-xs text-ink-muted">{{ $title }}</div>
                </{{ $goTo ? 'button' : 'div' }}>
            @endforeach
        </div>

        <div class="{{ $card }} p-6 shadow-sm">
            <div class="flex items-center gap-2.5 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">{!! $svg($icons['bulb'], 'w-5 h-5') !!}</span>
                <div>
                    <h3 class="font-semibold text-ink">Improve your score</h3>
                    <p class="text-xs text-ink-muted">Each of these adds points to the Security Score.</p>
                </div>
            </div>
            <ul class="grid grid-cols-1 md:grid-cols-2 gap-2">
                @foreach ($score['tips'] as $tip)
                    <li class="flex items-start gap-2 rounded-xl bg-surface-2 px-3 py-2.5 text-sm text-ink">
                        <span class="mt-0.5 flex-none text-red-500">{!! $svg('M8.25 4.5l7.5 7.5-7.5 7.5', 'w-4 h-4', '2.5') !!}</span>
                        {{ $tip }}
                    </li>
                @endforeach
            </ul>
            <button type="button" @click="openPolicy()" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700">
                {!! $svg($icons['lock'], 'w-4 h-4', '2') !!}
                {{ $policySaved ? 'Edit password policy' : 'Create password policy' }}
            </button>
        </div>
    </div>

    {{-- ============ Policy (read-only summary; edited in the Password Policies panel) ============ --}}
    <div x-show="secTab === 'policy'" x-cloak class="{{ $card }} p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
            <div>
                <h3 class="font-semibold text-ink">Security policy</h3>
                <p class="text-sm text-ink-muted mt-0.5">
                    @if ($policySaved)
                        The rules everyone follows. Stricter password rules apply the next time each person sets a password.
                    @else
                        No policy has been created yet, so the built-in defaults below apply.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="openPolicy(true)" class="inline-flex items-center gap-1.5 rounded-xl border border-line-strong bg-surface px-4 py-2.5 text-sm font-semibold text-ink hover:border-red-600 hover:text-red-600 transition-colors">
                    {!! $svg('M12 4.5v15m7.5-7.5h-15', 'w-4 h-4', '2.25') !!}
                    {{ $policySaved ? 'Start from a preset' : 'Create policy' }}
                </button>
                @if ($policySaved)
                    <button type="button" @click="openPolicy()" class="inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700">
                        {!! $svg('m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10', 'w-4 h-4', '2') !!}
                        Edit policy
                    </button>
                @endif
            </div>
        </div>

        @php
            $yes = fn (bool $on, string $text) => [$on, $text];
            $summary = [
                ['Passwords', $icons['key'], 'bg-red-500/10 text-red-500', [
                    [true, 'At least '.Policy::passwordMinLength().' characters'],
                    $yes(Policy::requiresMixedCase(), 'Upper and lower case letters'),
                    $yes(Policy::requiresNumbers(), 'At least one number'),
                    $yes(Policy::requiresSymbols(), 'At least one symbol'),
                    $yes(Policy::blocksBreached(), 'Breached passwords blocked'),
                ]],
                ['Password changes', $icons['clock'], 'bg-amber-500/10 text-amber-500', [
                    [Policy::passwordExpiryDays() > 0, Policy::passwordExpiryDays() > 0 ? 'Expire after '.Policy::passwordExpiryDays().' days' : 'Passwords never expire'],
                    [Policy::passwordHistory() > 0, Policy::passwordHistory() > 0 ? 'Last '.Policy::passwordHistory().' passwords blocked' : 'Old passwords can be reused'],
                    [true, 'Admin-set passwords are temporary'],
                ]],
                ['Login and sessions', $icons['shield'], 'bg-sky-500/10 text-sky-500', [
                    [true, 'Lock after '.Policy::lockoutAttempts().' failed attempts'],
                    [true, 'Locked for '.Policy::lockoutMinutes().' minutes'],
                    [true, 'Sign out after '.Policy::idleTimeoutMinutes().' idle minutes'],
                    [true, 'Admins notified of lockouts'],
                ]],
            ];
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            @foreach ($summary as [$title, $path, $toneClass, $rules])
                <section class="rounded-2xl border border-line p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $toneClass }}">{!! $svg($path, 'w-4 h-4', '2') !!}</span>
                        <span class="font-semibold text-ink">{{ $title }}</span>
                    </div>
                    <ul class="space-y-2 text-sm">
                        @foreach ($rules as [$on, $text])
                            <li class="flex items-center gap-2 {{ $on ? 'text-ink' : 'text-ink-faint' }}">
                                @if ($on)
                                    <span class="flex h-5 w-5 flex-none items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600">{!! $svg('M4.5 12.75l6 6 9-13.5', 'w-3 h-3', '3') !!}</span>
                                @else
                                    <span class="flex h-5 w-5 flex-none items-center justify-center rounded-full bg-surface-2 text-ink-faint">{!! $svg('M6 18 18 6M6 6l12 12', 'w-3 h-3', '3') !!}</span>
                                @endif
                                {{ $text }}
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </div>

    {{-- ============ People: who's signed in now, and who's signed out ============ --}}
    @php
        $statusStyles = [
            'online' => ['Online', 'bg-emerald-500/10 text-emerald-600', 'bg-emerald-500'],
            'idle' => ['Idle', 'bg-amber-500/10 text-amber-600', 'bg-amber-400'],
            'signed_out' => ['Signed out', 'bg-slate-500/10 text-slate-500', 'bg-slate-400'],
        ];
        $howLabels = [
            'logout' => 'Logged out',
            'admin' => 'Signed out by an admin',
            'expired' => 'Session expired',
            'never' => 'Never signed in',
        ];
    @endphp
    <div x-show="secTab === 'people'" x-cloak id="sec-people" data-sec-swap class="space-y-4 transition-opacity" :class="swapping && 'opacity-60 pointer-events-none'">
        {{-- Counts, each one a quick filter --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @foreach ([
                ['signed_in', 'Signed in now', $peopleCounts['online'] + $peopleCounts['idle'], 'bg-emerald-500/10 text-emerald-600', $peopleCounts['online'].' online · '.$peopleCounts['idle'].' idle'],
                ['signed_out', 'Signed out', $peopleCounts['signed_out'], 'bg-slate-500/10 text-slate-500', 'Not signed in on any device'],
                ['', 'Everyone', $peopleCounts['online'] + $peopleCounts['idle'] + $peopleCounts['signed_out'], 'bg-sky-500/10 text-sky-600', 'All staff accounts'],
            ] as [$value, $title, $count, $tone, $sub])
                <a href="{{ $secUrl(['sec_tab' => 'people', 'people_status' => $value ?: null]) }}"
                   class="{{ $card }} flex items-center gap-3 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ request('people_status', '') === $value ? 'ring-2 ring-red-500' : '' }}">
                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl {{ $tone }}">{!! $svg($peopleIcon, 'w-5 h-5', '1.9') !!}</span>
                    <span class="min-w-0">
                        <span class="block text-2xl font-bold tabular-nums text-ink">{{ $count }}</span>
                        <span class="block text-sm font-medium text-ink">{{ $title }}</span>
                        <span class="block text-xs text-ink-faint truncate">{{ $sub }}</span>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="{{ $card }} shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-line">
                <div>
                    <h3 class="font-semibold text-ink">People</h3>
                    <p class="text-xs text-ink-muted mt-0.5">Online = active in the last 5 minutes. Idle = still signed in, but inactive. Signed-out people show how they left.</p>
                </div>
                <form method="GET" action="{{ route('settings.index') }}" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="security">
                    <input type="hidden" name="sec_tab" value="people">
                    @if (request('people_status'))
                        <input type="hidden" name="people_status" value="{{ request('people_status') }}">
                    @endif
                    <input type="search" name="people_q" value="{{ request('people_q') }}" placeholder="Search name or email" class="bg-surface border-line-strong rounded-lg shadow-sm text-sm">
                    <button type="submit" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Search</button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left">
                            <th class="{{ $label }} px-5 py-3">Person</th>
                            <th class="{{ $label }} px-5 py-3">Status</th>
                            <th class="{{ $label }} px-5 py-3">Devices</th>
                            <th class="{{ $label }} px-5 py-3">Last signed in</th>
                            <th class="{{ $label }} px-5 py-3">Last active / signed out</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($people as $person)
                            @php [$statusText, $statusPill, $dot] = $statusStyles[$person->status]; @endphp
                            <tr class="border-t border-line align-middle hover:bg-surface-2" data-person-status="{{ $person->status }}">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="relative flex h-9 w-9 flex-none items-center justify-center rounded-full bg-surface-2 text-sm font-bold text-ink-muted">
                                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($person->user->name, 0, 1)) }}
                                            <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full ring-2 ring-surface {{ $dot }} {{ $person->status === 'online' ? 'animate-pulse' : '' }}" aria-hidden="true"></span>
                                        </span>
                                        <div class="min-w-0">
                                            <a href="{{ route('users.show', $person->user) }}" class="font-medium text-ink hover:text-accent-ink hover:underline">{{ $person->user->name }}</a>
                                            <div class="text-xs text-ink-faint truncate">{{ $person->user->email }} · {{ $person->user->roles->pluck('name')->join(', ') ?: 'No role' }}{{ $person->user->is_active ? '' : ' · Deactivated' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $statusPill }}">{{ $statusText }}</span>
                                    @if ($person->how)
                                        <div class="text-xs text-ink-muted mt-1">{{ $howLabels[$person->how] }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($person->devices)
                                        <a href="{{ $secUrl(['sec_tab' => 'devices', 'sess_user' => $person->user->id]) }}" class="inline-flex items-center gap-1.5 text-ink hover:text-accent-ink hover:underline">
                                            {!! $svg($icons['devices'], 'w-4 h-4') !!}
                                            {{ $person->devices }}
                                        </a>
                                    @else
                                        <span class="text-ink-faint">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    @if ($person->signed_in_at)
                                        <div class="text-ink" title="{{ $person->signed_in_at->format('Y-m-d H:i:s') }}">{{ $person->signed_in_at->diffForHumans() }}</div>
                                        <div class="font-mono text-[11px] text-ink-faint">{{ $person->signed_in_at->format('Y-m-d H:i') }}</div>
                                    @else
                                        <span class="text-ink-faint">Never</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    @if ($person->last_active)
                                        <div class="text-ink">Active {{ $person->last_active->diffForHumans() }}</div>
                                        <div class="font-mono text-[11px] text-ink-faint">{{ $person->last_active->format('Y-m-d H:i') }}</div>
                                    @elseif ($person->signed_out_at)
                                        <div class="text-ink">Signed out {{ $person->signed_out_at->diffForHumans() }}</div>
                                        <div class="font-mono text-[11px] text-ink-faint">{{ $person->signed_out_at->format('Y-m-d H:i') }}</div>
                                    @else
                                        <span class="text-ink-faint">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-ink-faint">Nobody matches.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============ Devices ============ --}}
    <div x-show="secTab === 'devices'" x-cloak id="sec-devices" data-sec-swap class="{{ $card }} shadow-sm transition-opacity" :class="swapping && 'opacity-60 pointer-events-none'">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-line">
            <div>
                <h3 class="font-semibold text-ink">Signed-in devices</h3>
                <p class="text-xs text-ink-muted mt-0.5">Newest activity first. Signing out a user's devices also cancels "Remember me" on all of them.</p>
            </div>
            <form method="POST" action="{{ route('security.sessions.revokeOthers') }}" onsubmit="return confirm('Sign out every other device, for everyone? You stay signed in here.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-red-600 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-600 hover:text-white transition-colors">
                    {!! $svg('M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9', 'w-4 h-4', '2') !!}
                    Sign out everyone except me
                </button>
            </form>
        </div>

        <form method="GET" action="{{ route('settings.index') }}" class="flex flex-wrap items-end gap-3 px-5 py-3 border-b border-line bg-surface-2">
            <input type="hidden" name="tab" value="security">
            <input type="hidden" name="sec_tab" value="devices">
            <div>
                <label for="sess_user" class="block text-xs text-ink-muted mb-1">User</label>
                <select id="sess_user" name="sess_user" class="bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm">
                    <option value="">Everyone</option>
                    @foreach ($deviceUsers as $u)
                        <option value="{{ $u->id }}" @selected(request('sess_user') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="sess_device" class="block text-xs text-ink-muted mb-1">Device</label>
                <select id="sess_device" name="sess_device" class="bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm">
                    <option value="">Any</option>
                    @foreach (['Desktop', 'Phone', 'Tablet', 'Unknown'] as $type)
                        <option value="{{ $type }}" @selected(request('sess_device') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-ink pb-2">
                <input type="checkbox" name="sess_recent" value="1" class="rounded border-line-strong text-red-600 focus:ring-red-500" @checked(request()->boolean('sess_recent'))>
                Active in the last 15 minutes
            </label>
            <button type="submit" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Filter</button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left">
                        <th class="{{ $label }} px-5 py-3">User</th>
                        <th class="{{ $label }} px-5 py-3">Device</th>
                        <th class="{{ $label }} px-5 py-3">IP address</th>
                        <th class="{{ $label }} px-5 py-3">Last active</th>
                        <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($devices as $device)
                        <tr class="border-t border-line align-middle hover:bg-surface-2">
                            <td class="px-5 py-3">
                                <a href="{{ route('users.show', $device->user) }}" class="font-medium text-ink hover:text-accent-ink hover:underline">{{ $device->user->name }}</a>
                                <div class="text-xs text-ink-faint">{{ $device->user->email }}</div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2.5" title="{{ $device->user_agent }}">
                                    <span class="w-9 h-9 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center flex-none">{!! $svg($deviceIcons[$device->agent['device']], 'w-[18px] h-[18px]') !!}</span>
                                    <div>
                                        <div class="text-ink">{{ $device->agent['device'] === 'Unknown' ? 'Unknown device' : $device->agent['device'] }}</div>
                                        <div class="text-xs text-ink-faint">{{ $device->agent['browser'] }} on {{ $device->agent['os'] }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 font-mono text-xs text-ink-muted">{{ $device->ip ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <div class="text-ink" title="{{ $device->last_activity->format('Y-m-d H:i:s') }}">{{ $device->last_activity->diffForHumans() }}</div>
                                @if ($device->is_current)
                                    <span class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded-full bg-success-soft text-success">This device</span>
                                @elseif ($device->idle_soon)
                                    <span class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded-full bg-pill-bg text-pill-ink">Idle</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @unless ($device->is_current)
                                    <div class="inline-flex items-center gap-2">
                                        <form method="POST" action="{{ route('security.sessions.revoke', $device->id) }}" onsubmit="return confirm('Sign out this device?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-line-strong bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink-muted hover:text-critical hover:border-critical">Sign out</button>
                                        </form>
                                        @if ($device->user->id !== auth()->id())
                                            <form method="POST" action="{{ route('security.users.sessions.revoke', $device->user) }}" onsubmit="return confirm('Sign {{ e($device->user->name) }} out of ALL their devices?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-line-strong bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink-muted hover:text-critical hover:border-critical">All devices</button>
                                            </form>
                                        @endif
                                    </div>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-ink-faint">No signed-in devices match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============ Login activity ============ --}}
    <div x-show="secTab === 'activity'" x-cloak id="sec-activity" data-sec-swap class="{{ $card }} shadow-sm min-w-0 transition-opacity" :class="swapping && 'opacity-60 pointer-events-none'">
        <div class="px-5 py-4 border-b border-line">
            <h3 class="font-semibold text-ink">Login activity</h3>
            <p class="text-xs text-ink-muted mt-0.5">Every failed attempt is kept with the email typed, IP address and time. Passwords are never stored.</p>
        </div>

        <div class="flex gap-1 overflow-x-auto px-5 pt-3 border-b border-line">
            @foreach ($eventTabs as $key => $text)
                <a href="{{ $secUrl(['sec_tab' => 'activity', 'ev_event' => $key]) }}"
                   class="px-3 py-2 text-sm font-semibold whitespace-nowrap border-b-2 {{ $eventFilter === $key ? 'text-red-600 border-red-600' : 'text-ink-faint border-transparent hover:text-ink' }}">{{ $text }}</a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('settings.index') }}" class="grid grid-cols-2 md:grid-cols-6 gap-3 items-end px-5 py-3 border-b border-line bg-surface-2">
            <input type="hidden" name="tab" value="security">
            <input type="hidden" name="sec_tab" value="activity">
            <input type="hidden" name="ev_event" value="{{ $eventFilter }}">
            <div class="col-span-2">
                <label for="ev_email" class="block text-xs text-ink-muted mb-1">Email</label>
                <input id="ev_email" name="ev_email" type="text" value="{{ request('ev_email') }}" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm" placeholder="Any">
            </div>
            <div>
                <label for="ev_ip" class="block text-xs text-ink-muted mb-1">IP address</label>
                <input id="ev_ip" name="ev_ip" type="text" value="{{ request('ev_ip') }}" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm font-mono" placeholder="Any">
            </div>
            <div>
                <label for="ev_reason" class="block text-xs text-ink-muted mb-1">Reason</label>
                <select id="ev_reason" name="ev_reason" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm">
                    <option value="">Any</option>
                    @foreach (['wrong_password', 'unknown_email', 'deactivated', 'too_many_attempts'] as $reason)
                        <option value="{{ $reason }}" @selected(request('ev_reason') === $reason)>{{ LoginEvent::REASON_LABELS[$reason] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ev_from" class="block text-xs text-ink-muted mb-1">From</label>
                <input id="ev_from" name="ev_from" type="date" value="{{ request('ev_from') }}" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm">
            </div>
            <div>
                <label for="ev_to" class="block text-xs text-ink-muted mb-1">To</label>
                <input id="ev_to" name="ev_to" type="date" value="{{ request('ev_to') }}" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm">
            </div>
            <div class="col-span-2 md:col-span-6 flex gap-2">
                <button type="submit" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Filter</button>
                <a href="{{ $secUrl(['sec_tab' => 'activity', 'ev_event' => $eventFilter]) }}" class="rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm font-semibold text-ink-muted hover:text-ink">Clear</a>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left">
                        <th class="{{ $label }} px-5 py-3">Time</th>
                        <th class="{{ $label }} px-5 py-3">Event</th>
                        <th class="{{ $label }} px-5 py-3">Email</th>
                        <th class="{{ $label }} px-5 py-3">IP address</th>
                        <th class="{{ $label }} px-5 py-3">Device</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        @php $agent = UserAgent::describe($event->user_agent); @endphp
                        <tr class="border-t border-line align-top hover:bg-surface-2">
                            <td class="px-5 py-3 whitespace-nowrap">
                                <div class="font-mono text-xs text-ink">{{ $event->created_at->format('Y-m-d H:i:s') }}</div>
                                <div class="text-xs text-ink-faint">{{ $event->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded-full whitespace-nowrap {{ $eventPill[$event->event] ?? 'bg-pill-bg text-pill-ink' }}">{{ LoginEvent::EVENT_LABELS[$event->event] ?? $event->event }}</span>
                                @if ($event->reason)
                                    <div class="text-xs text-ink-muted mt-1">{{ LoginEvent::REASON_LABELS[$event->reason] ?? $event->reason }}</div>
                                @endif
                                @if ($event->actor)
                                    <div class="text-xs text-ink-faint mt-0.5">by {{ $event->actor->name }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-3 min-w-0">
                                @if ($event->user)
                                    <a href="{{ route('users.show', $event->user) }}" class="text-ink hover:text-accent-ink hover:underline break-all">{{ $event->email ?? $event->user->email }}</a>
                                @else
                                    <span class="text-ink break-all">{{ $event->email ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if ($event->ip_address)
                                    <a href="{{ $secUrl(['sec_tab' => 'activity', 'ev_event' => 'all', 'ev_ip' => $event->ip_address]) }}" class="font-mono text-xs text-ink-muted hover:text-accent-ink hover:underline">{{ $event->ip_address }}</a>
                                @else
                                    <span class="text-ink-faint">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-ink-muted whitespace-nowrap" title="{{ $event->user_agent }}">{{ $agent['browser'] }} · {{ $agent['os'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-ink-faint">Nothing recorded for these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($events->hasPages())
            <div class="px-5 py-3 border-t border-line">{{ $events->appends(['sec_tab' => 'activity'])->links() }}</div>
        @endif
    </div>

    {{-- ============ Threats ============ --}}
    <div x-show="secTab === 'threats'" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach ([['IP addresses', $topIps, 'ip_address', 'ev_ip', 'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418'], ['Emails', $topEmails, 'email', 'ev_email', 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75']] as [$title, $rows, $column, $param, $path])
            <div class="{{ $card }} shadow-sm">
                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-line">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-500/10 text-red-500">{!! $svg($path, 'w-[18px] h-[18px]') !!}</span>
                    <div>
                        <h3 class="font-semibold text-ink">Most failed attempts · {{ $title }}</h3>
                        <p class="text-xs text-ink-muted">Last 24 hours</p>
                    </div>
                </div>
                <ul class="divide-y divide-line">
                    @forelse ($rows as $row)
                        @php $max = max(1, $rows->max('attempts')); @endphp
                        <li class="px-5 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <a href="{{ $secUrl(['sec_tab' => 'activity', 'ev_event' => 'failed', $param => $row->{$column}]) }}" class="min-w-0 truncate text-sm text-ink hover:text-accent-ink hover:underline {{ $column === 'ip_address' ? 'font-mono text-xs' : '' }}">{{ $row->{$column} ?? 'Unknown' }}</a>
                                <span class="flex-none font-mono text-xs font-semibold px-2 py-0.5 rounded-full {{ $row->attempts >= Policy::lockoutAttempts() ? 'bg-critical-soft text-critical' : 'bg-pill-bg text-pill-ink' }}">{{ $row->attempts }} {{ \Illuminate\Support\Str::plural('attempt', $row->attempts) }}</span>
                            </div>
                            <div class="mt-2 h-1.5 rounded-full bg-surface-2 overflow-hidden" aria-hidden="true">
                                <div class="h-full rounded-full {{ $row->attempts >= Policy::lockoutAttempts() ? 'bg-red-500' : 'bg-amber-400' }}" style="width: {{ round($row->attempts / $max * 100) }}%"></div>
                            </div>
                        </li>
                    @empty
                        <li class="flex flex-col items-center gap-2 px-5 py-10 text-center">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500">{!! $svg($icons['shield'], 'w-5 h-5', '2') !!}</span>
                            <span class="text-sm text-ink-faint">No failed attempts in the last 24 hours.</span>
                        </li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>

    {{-- ============ Password Policies panel ============
         One policy for everyone. "Create" starts from a preset (which only
         fills the form); "Edit" opens the current values. Nothing is saved
         until the Admin clicks Save. --}}
    <div x-show="policyOpen" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="policy-panel-title">
        <div x-show="policyOpen" x-transition.opacity class="absolute inset-0 bg-black/40" @click="policyOpen = false"></div>

        <div x-show="policyOpen"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
            class="absolute inset-y-0 right-0 flex w-full sm:w-[34rem] flex-col bg-surface border-l border-line shadow-2xl">

            {{-- Panel header --}}
            <div class="flex items-center justify-between gap-3 bg-gradient-to-r from-red-600 to-red-800 px-5 py-4">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-white text-red-600">{!! $svg($icons['lock'], 'w-5 h-5', '2') !!}</span>
                    <div class="min-w-0">
                        <h3 id="policy-panel-title" class="font-semibold text-white truncate"
                            x-text="creating ? (mode === 'pick' ? 'Create a password policy' : (chosenPreset ? 'New policy from ' + presets[chosenPreset].name : 'New policy')) : 'Edit password policy'"></h3>
                        <p class="text-xs text-red-100"
                            x-text="mode === 'pick' ? 'Start from a preset, then adjust anything before saving.' : 'Applies to everyone. Changes take effect the next time a password is set.'"></p>
                    </div>
                </div>
                <button type="button" @click="policyOpen = false" class="flex-none rounded-lg p-1.5 text-white/80 hover:bg-white/10 hover:text-white" aria-label="Close">{!! $svg('M6 18 18 6M6 6l12 12', 'w-5 h-5', '2') !!}</button>
            </div>

            {{-- Step 1: pick a preset --}}
            <div x-show="mode === 'pick'" class="flex-1 overflow-y-auto p-5 space-y-3">
                @foreach (Policy::PRESETS as $key => $preset)
                    @php $v = $preset['values']; @endphp
                    <button type="button" @click="usePreset('{{ $key }}')" class="group w-full text-left rounded-2xl border border-line-strong bg-surface p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-red-500 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl {{ $presetTones[$key] }}">{!! $svg($presetIcons[$key], 'w-5 h-5', '2') !!}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-ink">{{ $preset['name'] }}</span>
                                    @if ($key === 'recommended')
                                        <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-600">Recommended</span>
                                    @endif
                                </div>
                                <p class="text-xs text-ink-muted mt-0.5">{{ $preset['summary'] }}</p>
                                <div class="mt-2.5 flex flex-wrap gap-1.5">
                                    @foreach ([
                                        $v['password_min_length'].'+ characters',
                                        $v['password_expiry_days'] ? 'Expires in '.$v['password_expiry_days'].' days' : 'No expiry',
                                        'Lock after '.$v['lockout_attempts'].' tries',
                                        $v['idle_timeout_minutes'].' min idle',
                                    ] as $chip)
                                        <span class="rounded-md bg-surface-2 px-2 py-0.5 text-[11px] text-ink-muted">{{ $chip }}</span>
                                    @endforeach
                                </div>
                            </div>
                            <span class="flex-none self-center text-ink-faint transition group-hover:translate-x-0.5 group-hover:text-red-500">{!! $svg('M8.25 4.5l7.5 7.5-7.5 7.5', 'w-5 h-5', '2') !!}</span>
                        </div>
                    </button>
                @endforeach

                <button type="button" @click="useCurrent()" class="w-full rounded-2xl border border-dashed border-line-strong px-4 py-3 text-sm font-semibold text-ink-muted hover:border-red-500 hover:text-red-600 transition-colors">
                    {{ $policySaved ? 'Keep the current settings and edit them' : 'Start from the built-in defaults' }}
                </button>
            </div>

            {{-- Step 2: the form (create from a preset, or edit) --}}
            <form x-show="mode === 'form'" method="POST" action="{{ route('settings.update') }}" class="flex flex-1 min-h-0 flex-col" novalidate>
                @csrf
                @method('PUT')
                <input type="hidden" name="group" value="security">
                <input type="hidden" name="policy_mode" :value="creating ? 'create' : 'edit'">

                <div class="flex-1 overflow-y-auto p-5 space-y-6">
                    <div class="flex items-center justify-between gap-2">
                        <button type="button" x-show="creating" @click="mode = 'pick'" class="inline-flex items-center gap-1 text-xs font-semibold text-ink-muted hover:text-red-600">
                            {!! $svg('M15.75 19.5 8.25 12l7.5-7.5', 'w-4 h-4', '2') !!}
                            Choose a different preset
                        </button>
                        <button type="button" x-show="! creating" @click="creating = true; mode = 'pick'" class="inline-flex items-center gap-1 text-xs font-semibold text-ink-muted hover:text-red-600">
                            {!! $svg('M12 4.5v15m7.5-7.5h-15', 'w-4 h-4', '2.25') !!}
                            Start from a preset instead
                        </button>
                    </div>

                    <section class="space-y-4">
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-500/10 text-red-500">{!! $svg($icons['key'], 'w-4 h-4', '2') !!}</span>
                            <span class="font-semibold text-ink">Passwords</span>
                        </div>
                        <div>
                            <x-input-label for="pp_min_length" value="Minimum length" />
                            <input id="pp_min_length" name="password_min_length" type="number" min="8" max="32" x-model.number="form.password_min_length" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-red-500 focus:ring-red-500">
                            <p class="text-xs text-ink-faint mt-1">8 to 32 characters.</p>
                            <x-input-error :messages="$errors->get('password_min_length')" class="mt-1.5" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach ([
                                'password_require_mixed_case' => 'Upper and lower case',
                                'password_require_numbers' => 'At least one number',
                                'password_require_symbols' => 'At least one symbol',
                                'password_block_breached' => 'Block breached passwords',
                            ] as $field => $text)
                                <label class="flex items-center gap-2 rounded-xl border border-line px-3 py-2.5 text-sm text-ink cursor-pointer has-[:checked]:border-red-500 has-[:checked]:bg-red-500/5 transition-colors">
                                    <input type="checkbox" name="{{ $field }}" value="1" x-model="form.{{ $field }}" class="rounded border-line-strong text-red-600 focus:ring-red-500">
                                    {{ $text }}
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-ink-faint">The breach check sends only the first 5 characters of a hash of the password to Have I Been Pwned, never the password itself.</p>
                    </section>

                    <section class="space-y-4">
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500">{!! $svg($icons['clock'], 'w-4 h-4', '2') !!}</span>
                            <span class="font-semibold text-ink">Password changes</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <x-input-label for="pp_expiry" value="Expire after (days)" />
                                <input id="pp_expiry" name="password_expiry_days" type="number" min="0" max="365" x-model.number="form.password_expiry_days" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-red-500 focus:ring-red-500">
                                <p class="text-xs text-ink-faint mt-1">0 = never.</p>
                                <x-input-error :messages="$errors->get('password_expiry_days')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="pp_history" value="Block last (passwords)" />
                                <input id="pp_history" name="password_history" type="number" min="0" max="12" x-model.number="form.password_history" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-red-500 focus:ring-red-500">
                                <p class="text-xs text-ink-faint mt-1">0 = off.</p>
                                <x-input-error :messages="$errors->get('password_history')" class="mt-1.5" />
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-500/10 text-sky-500">{!! $svg($icons['shield'], 'w-4 h-4', '2') !!}</span>
                            <span class="font-semibold text-ink">Login and sessions</span>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <x-input-label for="pp_attempts" value="Failed attempts" />
                                <input id="pp_attempts" name="lockout_attempts" type="number" min="3" max="20" x-model.number="form.lockout_attempts" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-red-500 focus:ring-red-500">
                                <x-input-error :messages="$errors->get('lockout_attempts')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="pp_lock" value="Lock (min)" />
                                <input id="pp_lock" name="lockout_minutes" type="number" min="1" max="1440" x-model.number="form.lockout_minutes" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-red-500 focus:ring-red-500">
                                <x-input-error :messages="$errors->get('lockout_minutes')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="pp_idle" value="Idle (min)" />
                                <input id="pp_idle" name="idle_timeout_minutes" type="number" min="5" max="1440" x-model.number="form.idle_timeout_minutes" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-red-500 focus:ring-red-500">
                                <x-input-error :messages="$errors->get('idle_timeout_minutes')" class="mt-1.5" />
                            </div>
                        </div>
                        <p class="text-xs text-ink-faint">Admins get an in-app notification whenever an account is locked.</p>
                    </section>
                </div>

                {{-- Pinned footer --}}
                <div class="flex flex-none items-center justify-end gap-2 border-t border-line bg-surface px-5 py-3.5">
                    <button type="button" @click="policyOpen = false" class="inline-flex items-center rounded-lg border border-line-strong bg-surface px-3.5 py-2 text-sm font-medium text-ink-muted shadow-sm hover:bg-surface-2 hover:text-ink">Cancel</button>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700">
                        {!! $svg('M4.5 12.75l6 6 9-13.5', 'w-4 h-4', '2.5') !!}
                        <span x-text="creating ? 'Create policy' : 'Save changes'">Save changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
