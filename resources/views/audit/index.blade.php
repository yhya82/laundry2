<x-app-layout>
    <x-slot name="header">Audit Log</x-slot>

    {{--
        One timeline: data changes (DB triggers) and, for Admins, security events
        (the Security Center's log). Entries come from AuditController via
        AuditPresenter. Filters, tabs and pagination update the results in place.
    --}}
    @php
        $icons = [
            'audit' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z',
            'download' => 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3',
            'all' => 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z',
            'data' => 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125',
            'shield' => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
            'created' => 'M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'updated' => 'm16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125',
            'deleted' => 'm14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0',
            'role' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z',
            'login' => 'M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75',
            'logout' => 'M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9',
            'failed' => 'm9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'lock' => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
            'key' => 'M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.03 5.91c-.56-.1-1.16.02-1.56.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.82c0-.6.24-1.17.66-1.59l6.41-6.41c.41-.4.53-1 .43-1.56A6 6 0 1 1 21.75 8.25Z',
            'filter' => 'M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z',
            'empty' => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m6 4.125 2.25 2.25m0 0 2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z',
            'link' => 'M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25',
        ];
        $moduleIcons = [
            'order' => 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
            'customer' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
            'subscription' => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99',
            'payment' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z',
            'expense' => 'M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
            'damage' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
            'catalog' => 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z M6 6h.008v.008H6V6Z',
            'user' => $icons['role'],
            'setting' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
            'shield' => $icons['shield'],
            'other' => $icons['data'],
        ];
        $tones = [
            'emerald' => 'bg-emerald-500/10 text-emerald-600',
            'sky' => 'bg-sky-500/10 text-sky-600',
            'red' => 'bg-red-500/10 text-red-600',
            'violet' => 'bg-violet-500/10 text-violet-600',
            'slate' => 'bg-slate-500/10 text-slate-500',
        ];
        $svg = fn (string $path, string $class = 'w-5 h-5', string $width = '1.8') => '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="'.$width.'" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="'.$path.'" /></svg>';
        $tabs = $isAdmin
            ? ['all' => ['All activity', $icons['all']], 'data' => ['Data changes', $icons['data']], 'security' => ['Security', $icons['shield']]]
            : ['data' => ['Data changes', $icons['data']]];
        $filtered = request()->anyFilled(['module', 'action', 'user', 'from', 'to', 'q']);
    @endphp

    <div x-data="{
        entry: null,
        swapping: false,
        open(e) { this.entry = e; },
        async swap(url) {
            this.swapping = true;
            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (! res.ok || res.redirected) { window.location = url; return; }
                const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                const fresh = doc.getElementById('audit-results');
                if (! fresh) { window.location = url; return; }
                this.$refs.results.innerHTML = fresh.innerHTML;
                history.pushState({}, '', url);
            } catch (e) {
                window.location = url;
            } finally {
                this.swapping = false;
            }
        },
        isAuditUrl(url) {
            const u = new URL(url, window.location.href);
            return u.origin === window.location.origin && u.pathname === window.location.pathname;
        },
        interceptLink(event) {
            const a = event.target.closest('a[href]');
            if (! a || a.target || a.hasAttribute('download') || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
            if (! this.isAuditUrl(a.href)) return;
            event.preventDefault();
            this.swap(a.href);
        },
        interceptForm(event) {
            const form = event.target;
            if ((form.method || 'get').toLowerCase() !== 'get') return;
            const params = new URLSearchParams(new FormData(form));
            [...params.keys()].forEach((k) => params.get(k) === '' && params.delete(k));
            const url = form.action.split('?')[0] + '?' + params.toString();
            if (! this.isAuditUrl(url)) return;
            event.preventDefault();
            this.swap(url);
        },
    }"
        x-on:click="interceptLink($event)"
        x-on:submit="interceptForm($event)"
        x-on:popstate.window="window.location.reload()"
        x-on:keydown.escape.window="entry = null"
        class="space-y-6">

        {{-- Header --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-800 via-slate-800 to-indigo-800 px-6 py-6 sm:px-8 shadow-lg">
            <div class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-indigo-400/20 blur-2xl" aria-hidden="true"></div>
            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <span class="flex h-14 w-14 flex-none items-center justify-center rounded-2xl bg-white text-indigo-700 shadow-md">{!! $svg($icons['audit'], 'w-7 h-7', '1.8') !!}</span>
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">Audit Log</h2>
                        <p class="text-sm sm:text-base text-indigo-100">Every change{{ $isAdmin ? ' and sign-in' : '' }}, who made it, and when</p>
                    </div>
                </div>
                <a href="{{ route('audit.export', request()->query()) }}" download class="inline-flex items-center gap-2 self-start lg:self-auto rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50 hover:shadow">
                    {!! $svg($icons['download'], 'w-[18px] h-[18px]', '2') !!}
                    Export CSV
                </a>
            </div>
        </div>

        <div x-ref="results" id="audit-results" class="space-y-6 transition-opacity" :class="swapping && 'opacity-60 pointer-events-none'">
            {{-- Tabs --}}
            <div class="flex gap-1 overflow-x-auto rounded-2xl border border-line bg-surface p-1.5 shadow-sm" role="tablist">
                @foreach ($tabs as $key => [$text, $path])
                    <a href="{{ route('audit.index', array_filter(['tab' => $key, ...request()->only(['user', 'from', 'to', 'q'])])) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                       class="inline-flex flex-none items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold whitespace-nowrap transition-colors {{ $tab === $key ? 'bg-indigo-700 text-white shadow-sm' : 'text-ink-muted hover:bg-surface-2 hover:text-ink' }}">
                        {!! $svg($path, 'w-[18px] h-[18px]') !!}
                        {{ $text }}
                    </a>
                @endforeach
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('audit.index') }}" class="rounded-2xl border border-line bg-surface p-4 shadow-sm">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 items-end">
                    <div class="col-span-2">
                        <label for="audit_q" class="block text-xs text-ink-muted mb-1">Search</label>
                        <input id="audit_q" name="q" type="search" value="{{ request('q') }}" placeholder="Order number, name, email{{ $isAdmin ? ', IP' : '' }}…" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm">
                    </div>
                    @if ($tab !== 'security')
                        <div>
                            <label for="audit_module" class="block text-xs text-ink-muted mb-1">Module</label>
                            <select id="audit_module" name="module" class="w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm">
                                <option value="">All modules</option>
                                @foreach ($modules as $module)
                                    <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
                                @endforeach
                                @if ($isAdmin && $tab === 'all')
                                    <option value="Security" @selected(request('module') === 'Security')>Security</option>
                                @endif
                            </select>
                        </div>
                    @endif
                    <div>
                        <label for="audit_action" class="block text-xs text-ink-muted mb-1">Action</label>
                        <select id="audit_action" name="action" class="w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm">
                            <option value="">All actions</option>
                            @foreach ($actions as $value => $text)
                                <option value="{{ $value }}" @selected(request('action') === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="audit_user" class="block text-xs text-ink-muted mb-1">User</label>
                        <select id="audit_user" name="user" class="w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm">
                            <option value="">Everyone</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected((string) request('user') === (string) $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2 col-span-2 md:col-span-1 xl:col-span-1">
                        <div>
                            <label for="audit_from" class="block text-xs text-ink-muted mb-1">From</label>
                            <input id="audit_from" name="from" type="date" value="{{ request('from') }}" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm">
                        </div>
                        <div>
                            <label for="audit_to" class="block text-xs text-ink-muted mb-1">To</label>
                            <input id="audit_to" name="to" type="date" value="{{ request('to') }}" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm">
                        </div>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-indigo-800">
                        {!! $svg($icons['filter'], 'w-4 h-4', '2') !!}
                        Filter
                    </button>
                    @if ($filtered)
                        <a href="{{ route('audit.index', ['tab' => $tab]) }}" class="rounded-lg border border-line-strong bg-surface px-3.5 py-2 text-sm font-semibold text-ink-muted hover:text-ink">Clear</a>
                    @endif
                    <span class="ml-auto text-xs text-ink-faint">{{ number_format($entries->total()) }} {{ \Illuminate\Support\Str::plural('entry', $entries->total()) }}</span>
                </div>
            </form>

            {{-- Timeline --}}
            <div class="rounded-2xl border border-line bg-surface shadow-sm overflow-hidden">
                <ul class="divide-y divide-line">
                    @forelse ($entries as $entry)
                        <li>
                            <button type="button" @click="open(@js($entry))" class="group flex w-full items-start gap-3 px-4 py-3.5 text-left transition-colors hover:bg-surface-2 sm:px-5">
                                <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl {{ $tones[$entry['tone']] }}">{!! $svg($icons[$entry['icon']] ?? $icons['shield'], 'w-5 h-5', '1.9') !!}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-ink group-hover:text-indigo-700">{{ $entry['title'] }}</span>
                                    <span class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-ink-muted">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-surface-2 px-1.5 py-0.5">
                                            {!! $svg($moduleIcons[$entry['module_icon']] ?? $moduleIcons['other'], 'w-3 h-3', '2') !!}
                                            {{ $entry['module'] }}
                                        </span>
                                        <span class="rounded-md px-1.5 py-0.5 font-semibold {{ $tones[$entry['tone']] }}">{{ $entry['action'] }}</span>
                                        <span>by <span class="font-medium text-ink">{{ $entry['who'] }}</span></span>
                                        @foreach (array_slice($entry['changes'], 0, 3) as $change)
                                            <span class="hidden md:inline-flex rounded-md border border-line px-1.5 py-0.5 font-mono text-[11px]">
                                                {{ $change['field'] }}:
                                                @if ($change['before'] !== null)<span class="text-ink-faint line-through decoration-1">{{ \Illuminate\Support\Str::limit($change['before'], 24) }}</span>&nbsp;→&nbsp;@endif{{ \Illuminate\Support\Str::limit($change['after'] ?? '—', 24) }}
                                            </span>
                                        @endforeach
                                        @if (count($entry['changes']) > 3)
                                            <span class="hidden md:inline text-ink-faint">+{{ count($entry['changes']) - 3 }} more</span>
                                        @endif
                                        @if (isset($entry['meta']['IP address']))
                                            <span class="font-mono text-[11px] text-ink-faint">{{ $entry['meta']['IP address'] }}</span>
                                        @endif
                                    </span>
                                </span>
                                <span class="flex-none text-right">
                                    <span class="block font-mono text-xs text-ink">{{ \Illuminate\Support\Str::of($entry['when'])->after(' ') }}</span>
                                    <span class="block text-[11px] text-ink-faint">{{ \Illuminate\Support\Str::of($entry['when'])->before(' ') }}</span>
                                </span>
                            </button>
                        </li>
                    @empty
                        <li class="flex flex-col items-center gap-2 px-5 py-14 text-center">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-surface-2 text-ink-faint">{!! $svg($icons['empty'], 'w-6 h-6') !!}</span>
                            <span class="text-sm text-ink-faint">{{ $filtered ? 'Nothing matches these filters.' : 'No activity recorded yet.' }}</span>
                        </li>
                    @endforelse
                </ul>

                @if ($entries->hasPages())
                    <div class="border-t border-line px-5 py-3">{{ $entries->links() }}</div>
                @endif
            </div>
        </div>

        {{-- Details drawer --}}
        <div x-show="entry" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="audit-entry-title">
            <div x-show="entry" x-transition.opacity class="absolute inset-0 bg-black/40" @click="entry = null"></div>
            <div x-show="entry"
                x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                class="absolute inset-y-0 right-0 flex w-full sm:w-[32rem] flex-col bg-surface border-l border-line shadow-2xl">
                <template x-if="entry">
                    <div class="flex min-h-0 flex-1 flex-col">
                        <div class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                    <span class="rounded-md bg-surface-2 px-1.5 py-0.5 text-ink-muted" x-text="entry.module"></span>
                                    <span class="rounded-md px-1.5 py-0.5 font-semibold"
                                        :class="{ 'bg-emerald-500/10 text-emerald-600': entry.tone === 'emerald', 'bg-sky-500/10 text-sky-600': entry.tone === 'sky', 'bg-red-500/10 text-red-600': entry.tone === 'red', 'bg-violet-500/10 text-violet-600': entry.tone === 'violet', 'bg-slate-500/10 text-slate-500': entry.tone === 'slate' }"
                                        x-text="entry.action"></span>
                                </div>
                                <h3 id="audit-entry-title" class="mt-2 font-semibold text-ink" x-text="entry.title"></h3>
                                <p class="mt-1 text-xs text-ink-muted">
                                    by <span class="font-medium text-ink" x-text="entry.who"></span>
                                    · <span class="font-mono" x-text="entry.when"></span>
                                    (<span x-text="entry.ago"></span>)
                                </p>
                            </div>
                            <button type="button" @click="entry = null" class="flex-none rounded-lg p-1.5 text-ink-faint hover:bg-surface-2 hover:text-ink" aria-label="Close">{!! $svg('M6 18 18 6M6 6l12 12', 'w-5 h-5', '2') !!}</button>
                        </div>

                        <div class="flex-1 overflow-y-auto p-5 space-y-5">
                            <template x-if="entry.changes.length">
                                <div>
                                    <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-2">What changed</div>
                                    <div class="overflow-hidden rounded-xl border border-line">
                                        <table class="w-full text-sm">
                                            <thead class="bg-surface-2">
                                                <tr class="text-left text-xs text-ink-muted">
                                                    <th class="px-3 py-2 font-semibold">Field</th>
                                                    <th class="px-3 py-2 font-semibold">Before</th>
                                                    <th class="px-3 py-2 font-semibold">After</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="change in entry.changes" :key="change.field">
                                                    <tr class="border-t border-line align-top">
                                                        <td class="px-3 py-2 text-ink-muted whitespace-nowrap" x-text="change.field"></td>
                                                        <td class="px-3 py-2 text-ink-faint break-words" :class="change.before !== null && 'line-through decoration-1'" x-text="change.before ?? '—'"></td>
                                                        <td class="px-3 py-2 font-medium text-ink break-words" x-text="change.after ?? '—'"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p x-show="entry.legacy" class="mt-2 text-xs text-ink-faint">Recorded before before-and-after tracking existed, so only the values after the change are known.</p>
                                </div>
                            </template>

                            <template x-if="Object.keys(entry.meta).length">
                                <div>
                                    <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-2">Details</div>
                                    <dl class="divide-y divide-line rounded-xl border border-line text-sm">
                                        <template x-for="[label, value] in Object.entries(entry.meta)" :key="label">
                                            <div class="flex items-start justify-between gap-4 px-3 py-2">
                                                <dt class="text-ink-muted whitespace-nowrap" x-text="label"></dt>
                                                <dd class="text-right text-ink break-all" :class="label === 'IP address' && 'font-mono text-xs'" x-text="value"></dd>
                                            </div>
                                        </template>
                                    </dl>
                                </div>
                            </template>
                        </div>

                        <div x-show="entry.link" class="flex-none border-t border-line px-5 py-3.5">
                            <a :href="entry.link" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-800">
                                {!! $svg($icons['link'], 'w-4 h-4', '2') !!}
                                <span x-text="entry.link_label"></span>
                            </a>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-app-layout>
