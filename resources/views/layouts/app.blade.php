<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ auth()->user()?->theme_preference === 'dark' ? 'dark' : '' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ ($header ?? null) ? $header . ' — ' : '' }}{{ \App\Models\Setting::get('branding.business_name', config('app.name')) }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles

        <script>
            // Mirrors resources/views/components/status-pill.blade.php's tone/class
            // map exactly, so a live-patched pill is indistinguishable from one
            // that was server-rendered on page load.
            window.STATUS_PILL_TONES = {
                received: 'neutral', refunded: 'neutral', closed: 'neutral', pending_review: 'neutral', paused: 'neutral', normal: 'neutral',
                sorting: 'active', washing: 'active', drying: 'active', ironing: 'active', packaging: 'active',
                under_investigation: 'active', approved: 'active', partially_refunded: 'active', scheduled: 'active', partial: 'active',
                completed: 'success', resolved: 'success', active: 'success', collected: 'success', paid: 'success',
                cancelled: 'critical', rejected: 'critical', skipped: 'critical', unpaid: 'critical', high: 'critical',
            };
            window.STATUS_PILL_CLASSES = {
                neutral: 'bg-pill-bg text-pill-ink',
                active: 'bg-accent-soft text-accent-ink',
                success: 'bg-success-soft text-success',
                critical: 'bg-critical-soft text-critical',
            };
            window.applyStatusPill = function (el, status) {
                if (! el) return;
                const tone = window.STATUS_PILL_TONES[status] || 'neutral';
                el.className = 'inline-flex items-center gap-1.5 font-mono text-xs font-semibold px-2.5 py-1 rounded-full ' + window.STATUS_PILL_CLASSES[tone];
                el.textContent = status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, ' ');
            };
        </script>
    </head>
    <body class="font-sans antialiased bg-bg text-ink h-screen overflow-hidden" x-data="{ sidebarOpen: false }">
        <x-toast />
        <div class="h-full flex flex-col">

            <!-- Top nav -->
            <header class="h-16 flex-none border-b border-line bg-surface flex items-center px-4 gap-4">
                <div class="flex items-center gap-4 lg:w-60 lg:flex-none">
                    <button class="lg:hidden text-ink-muted" @click="sidebarOpen = !sidebarOpen" aria-label="Toggle navigation">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-bold text-ink">
                        @if ($logoPath = \App\Models\Setting::get('branding.logo_path'))
                            <img src="{{ Storage::disk('s3')->url($logoPath) }}" alt="" class="w-7 h-7 rounded object-cover">
                        @else
                            <span class="w-7 h-7 rounded bg-accent-soft text-accent-ink flex items-center justify-center text-sm font-bold">
                                {{ Str::substr(\App\Models\Setting::get('branding.business_name', config('app.name')), 0, 1) }}
                            </span>
                        @endif
                        <span class="hidden sm:inline">{{ \App\Models\Setting::get('branding.business_name', config('app.name')) }}</span>
                    </a>
                </div>

                @isset($header)
                    <div class="hidden sm:flex items-center gap-3">
                        <h1 class="text-base font-semibold text-ink">{{ $header }}</h1>
                        @isset($headerActions)
                            {{ $headerActions }}
                        @endisset
                    </div>
                @endisset

                <div class="ml-auto flex items-center gap-3">
                    <div
                        class="relative"
                        x-data="{
                            open: false,
                            items: @js(auth()->user() ? \App\Models\Notification::where('user_id', auth()->id())->latest()->limit(10)->get(['id', 'title', 'body', 'is_read', 'created_at'])->map(fn ($n) => ['id' => $n->id, 'title' => $n->title, 'body' => $n->body, 'is_read' => (bool) $n->is_read, 'time' => $n->created_at->diffForHumans()]) : []),
                            get unread() { return this.items.filter(i => !i.is_read).length; },
                            init() {
                                @auth
                                    window.Echo.channel('notifications.{{ auth()->id() }}').listen('.notification.created', (e) => {
                                        this.items.unshift({ id: e.id, title: e.title, body: e.body, is_read: false, time: 'just now' });
                                    });
                                @endauth
                            },
                            csrfHeaders() {
                                return { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' };
                            },
                            markRead(item) {
                                if (item.is_read) return;
                                item.is_read = true;
                                fetch(`/notifications/${item.id}/read`, { method: 'POST', headers: this.csrfHeaders() });
                            },
                            markAllRead() {
                                this.items.forEach(i => i.is_read = true);
                                fetch('/notifications/read-all', { method: 'POST', headers: this.csrfHeaders() });
                            },
                        }"
                        @click.outside="open = false"
                    >
                        <button type="button" @click="open = !open" class="relative w-9 h-9 rounded-full bg-surface-2 flex items-center justify-center text-ink-muted hover:text-ink" aria-label="Notifications">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span x-show="unread > 0" x-text="Math.min(unread, 9)" class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full bg-critical text-white text-[10px] font-mono font-bold flex items-center justify-center border-2 border-surface"></span>
                        </button>

                        <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-80 bg-surface border border-line rounded-xl shadow-lg z-30 max-h-96 overflow-y-auto">
                            <div class="flex items-center justify-between px-4 py-2.5 border-b border-line">
                                <span class="text-xs font-mono uppercase tracking-wide text-ink-faint">Notifications</span>
                                <button type="button" @click="markAllRead()" x-show="unread > 0" class="text-xs text-accent-ink hover:underline">Mark all read</button>
                            </div>
                            <template x-if="items.length === 0">
                                <p class="text-ink-faint text-sm px-4 py-4">Nothing yet.</p>
                            </template>
                            <template x-for="item in items" :key="item.id">
                                <button type="button" @click="markRead(item)" class="w-full text-left px-4 py-3 border-b border-line last:border-0 hover:bg-surface-2" :class="!item.is_read && 'bg-accent-soft/40'">
                                    <div class="flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full flex-none" :class="item.is_read ? 'bg-transparent' : 'bg-accent'"></span>
                                        <span class="text-sm font-semibold text-ink" x-text="item.title"></span>
                                    </div>
                                    <p class="text-xs text-ink-muted mt-1" x-text="item.body"></p>
                                    <p class="text-xs text-ink-faint mt-1" x-text="item.time"></p>
                                </button>
                            </template>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('theme.update') }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="w-9 h-9 rounded-full bg-surface-2 flex items-center justify-center text-ink-muted hover:text-ink" aria-label="Toggle theme">
                            @if (auth()->user()?->theme_preference === 'dark')
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.4 6.4l-.7-.7M6.3 6.3l-.7-.7m12.8 0l-.7.7M6.3 17.7l-.7.7M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                            @endif
                        </button>
                    </form>

                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="flex items-center gap-2 text-sm text-ink-muted hover:text-ink">
                                <span class="w-8 h-8 rounded-full bg-accent-soft text-accent-ink flex items-center justify-center text-xs font-bold">
                                    {{ Str::substr(auth()->user()->name ?? '?', 0, 1) }}
                                </span>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </header>

            <div class="flex-1 flex min-h-0">

                <!-- Sidebar -->
                <aside
                    class="w-64 flex-none bg-surface border-r border-line overflow-y-auto fixed lg:static inset-y-16 left-0 z-20 transition-transform lg:translate-x-0"
                    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                >
                    <nav class="p-3 flex flex-col gap-1">
                        @foreach (\App\Support\NavItems::all() as $item)
                            @if ($item['permission'] === null || auth()->user()?->can($item['permission']))
                                @if (isset($item['children']))
                                    <div x-data="{ open: {{ request()->routeIs('catalog.*') ? 'true' : 'false' }} }">
                                        <button @click="open = !open" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-ink-muted hover:bg-surface-2">
                                            <x-nav-icon :name="$item['icon']" />
                                            {{ $item['label'] }}
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 ml-auto transition-transform" :class="open && 'rotate-90'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                        </button>
                                        <div x-show="open" class="pl-8 flex flex-col gap-1 mt-1">
                                            @foreach ($item['children'] as $child)
                                                @if (auth()->user()?->can($child['permission']))
                                                    <a href="{{ route($child['route']) }}" class="px-3 py-1.5 rounded-lg text-sm {{ request()->routeIs($child['route']) ? 'bg-accent-soft text-accent-ink font-semibold' : 'text-ink-muted hover:bg-surface-2' }}">
                                                        {{ $child['label'] }}
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <a href="{{ route($item['route']) }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs($item['route']) ? 'bg-accent-soft text-accent-ink font-semibold' : 'text-ink-muted hover:bg-surface-2' }}">
                                        <x-nav-icon :name="$item['icon']" />
                                        {{ $item['label'] }}
                                    </a>
                                @endif
                            @endif
                        @endforeach

                        @php $adminItems = collect(\App\Support\NavItems::adminOnly())->filter(fn ($i) => auth()->user()?->can($i['permission'])); @endphp
                        @if ($adminItems->isNotEmpty())
                            <div class="h-px bg-line my-3"></div>
                            @foreach ($adminItems as $item)
                                <a href="{{ route($item['route']) }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs($item['route']) ? 'bg-accent-soft text-accent-ink font-semibold' : 'text-ink-muted hover:bg-surface-2' }}">
                                    <x-nav-icon :name="$item['icon']" />
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        @endif
                    </nav>
                </aside>

                <div class="flex-1 min-w-0 flex flex-col min-h-0">
                    <main class="flex-1 p-6 overflow-y-auto">
                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>
        @livewireScripts
    </body>
</html>
