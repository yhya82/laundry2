<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    <div class="bg-surface border border-line rounded-2xl p-8 mb-5">
        <p class="text-ink">
            Welcome back, <strong>{{ auth()->user()->name }}</strong>.
        </p>
        <p class="text-ink-muted text-sm mt-2">
            Role{{ auth()->user()->roles->count() > 1 ? 's' : '' }}:
            {{ auth()->user()->roles->pluck('name')->implode(', ') ?: 'none assigned' }}
            &middot; {{ auth()->user()->getAllPermissions()->count() }} permissions
        </p>
    </div>

    <div
        x-data="{
            todayRevenue: @js($todayRevenue),
            monthRevenue: @js($monthRevenue),
            yearRevenue: @js($yearRevenue),
            pendingOrders: @js($pendingOrders),
            queueCounts: @js((object) $queueCounts),
            pendingStages: @js(array_keys(\App\Models\Order::STAGE_SEQUENCE)),
            init() {
                window.Echo.channel('orders').listen('.order.status-changed', (e) => {
                    if (this.queueCounts === null) return;

                    if (e.fromStatus && this.pendingStages.includes(e.fromStatus)) {
                        this.queueCounts[e.fromStatus] = Math.max(0, (this.queueCounts[e.fromStatus] ?? 0) - 1);
                    }
                    if (this.pendingStages.includes(e.toStatus)) {
                        this.queueCounts[e.toStatus] = (this.queueCounts[e.toStatus] ?? 0) + 1;
                    }

                    if (this.pendingOrders !== null) {
                        const wasPending = e.fromStatus === null || this.pendingStages.includes(e.fromStatus);
                        const isPending = this.pendingStages.includes(e.toStatus);
                        if (!wasPending && isPending) this.pendingOrders++;
                        if (wasPending && !isPending) this.pendingOrders = Math.max(0, this.pendingOrders - 1);
                    }
                });

                window.Echo.channel('dashboard').listen('.payment.received', (e) => {
                    if (this.todayRevenue !== null && e.isToday) {
                        this.todayRevenue = parseFloat(this.todayRevenue) + parseFloat(e.amount);
                    }
                    if (this.monthRevenue !== null) {
                        this.monthRevenue = parseFloat(this.monthRevenue) + parseFloat(e.amount);
                    }
                    if (this.yearRevenue !== null) {
                        this.yearRevenue = parseFloat(this.yearRevenue) + parseFloat(e.amount);
                    }
                });
            }
        }"
    >
        @if ($todayRevenue !== null || $activeSubs !== null || $pendingOrders !== null || $monthExpenses !== null)
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
                @if ($todayRevenue !== null)
                    <div class="bg-surface border border-line rounded-2xl p-5" x-data="{ period: 'day' }">
                        <div class="flex items-center justify-between mb-1 gap-2">
                            <div class="font-mono text-xs uppercase tracking-wide text-ink-faint truncate" x-text="period === 'day' ? 'Today\'s Revenue' : period === 'month' ? 'Revenue (Month)' : 'Revenue (Year)'"></div>
                            <div class="flex gap-0.5 bg-surface-2 rounded-full p-0.5 flex-none">
                                <button type="button" @click="period = 'day'" class="px-2 py-0.5 rounded-full text-[10px] font-mono font-semibold uppercase" :class="period === 'day' ? 'bg-accent text-white' : 'text-ink-faint hover:text-ink'">Day</button>
                                <button type="button" @click="period = 'month'" class="px-2 py-0.5 rounded-full text-[10px] font-mono font-semibold uppercase" :class="period === 'month' ? 'bg-accent text-white' : 'text-ink-faint hover:text-ink'">Mo</button>
                                <button type="button" @click="period = 'year'" class="px-2 py-0.5 rounded-full text-[10px] font-mono font-semibold uppercase" :class="period === 'year' ? 'bg-accent text-white' : 'text-ink-faint hover:text-ink'">Yr</button>
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-accent-ink tabular-nums" x-text="'GMD ' + parseFloat(period === 'day' ? todayRevenue : period === 'month' ? monthRevenue : yearRevenue).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
                    </div>
                @endif
                @if ($activeSubs !== null)
                    <div class="bg-surface border border-line rounded-2xl p-5">
                        <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-1">Active Subs</div>
                        <div class="text-2xl font-bold text-ink tabular-nums">{{ $activeSubs }}</div>
                    </div>
                @endif
                @if ($pendingOrders !== null)
                    <div class="bg-surface border border-line rounded-2xl p-5">
                        <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-1">Pending Orders</div>
                        <div class="text-2xl font-bold text-ink tabular-nums" x-text="pendingOrders"></div>
                    </div>
                @endif
                @if ($monthExpenses !== null)
                    <div class="bg-surface border border-line rounded-2xl p-5">
                        <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-1">Expenses (MTD)</div>
                        <div class="text-2xl font-bold text-ink tabular-nums">GMD {{ number_format($monthExpenses, 2) }}</div>
                    </div>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
            @can('orders.view')
                <div class="bg-surface border border-line rounded-2xl p-6">
                    <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-4">Laundry Queue</div>
                    <div class="grid grid-cols-3 gap-3">
                        @foreach (array_keys(\App\Models\Order::STAGE_SEQUENCE) as $stage)
                            <div class="bg-surface-2 rounded-xl p-3">
                                <div class="text-xl font-bold text-ink tabular-nums" x-text="queueCounts['{{ $stage }}'] ?? 0"></div>
                                <div class="text-xs text-ink-muted mt-1">{{ ucfirst($stage) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endcan

            @if ($damageSnapshot !== null)
                <div class="bg-surface border border-line rounded-2xl p-6">
                    <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-4">Damage Snapshot</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-surface-2 rounded-xl p-3">
                            <div class="text-xl font-bold text-critical tabular-nums">{{ $damageSnapshot['pending'] }}</div>
                            <div class="text-xs text-ink-muted mt-1">Pending Review</div>
                        </div>
                        <div class="bg-surface-2 rounded-xl p-3">
                            <div class="text-xl font-bold text-success tabular-nums">{{ $damageSnapshot['resolved30d'] }}</div>
                            <div class="text-xs text-ink-muted mt-1">Resolved (30d)</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($revenueTrendSeries !== null)
        <div class="bg-surface border border-line rounded-2xl p-6" x-data="{
            init() {
                new Chart(this.$refs.canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: {{ Js::from($revenueTrendSeries->pluck('label')) }},
                        datasets: [{
                            data: {{ Js::from($revenueTrendSeries->pluck('total')) }},
                            borderColor: getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim(),
                            backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--color-accent-soft').trim(),
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3,
                        }],
                    },
                    options: {
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { callback: (v) => 'GMD ' + v } },
                        },
                    },
                });
            }
        }">
            <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-4">Revenue — Last 7 Days</div>
            <canvas x-ref="canvas" height="80"></canvas>
        </div>
    @endif
</x-app-layout>
