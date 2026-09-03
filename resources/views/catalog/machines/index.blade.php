<x-app-layout>
    <x-slot name="header">Washing Machines</x-slot>

    <div class="flex items-center justify-between mb-3">
        <p class="text-sm text-ink-muted max-w-xl">How many orders can wash at once — one per active machine. Retire a machine (rather than deleting it) if it breaks down; its history stays intact and it stops being offered until reactivated.</p>
        @can('catalog.manage')
            <x-panel-trigger panel="machine-create">+ Add Machine</x-panel-trigger>
        @endcan
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        @forelse ($washingMachines as $machine)
            @php
                $current = $machine->orders->firstWhere('status', 'washing');
                $status = ! $machine->is_active ? 'retired' : ($current ? 'washing' : 'idle');
                $statusLabel = ['idle' => 'Idle', 'washing' => 'Washing', 'retired' => 'Retired'][$status];
                $pillClass = ['idle' => 'bg-success-soft text-success', 'washing' => 'bg-accent-soft text-accent-ink', 'retired' => 'bg-pill-bg text-pill-ink'][$status];
            @endphp
            <button
                type="button"
                @click="$dispatch('open-panel', 'machine-{{ $machine->id }}')"
                class="bg-surface border border-line rounded-2xl p-3 text-left hover:border-accent/50 hover:shadow-sm transition-all"
            >
                <div class="flex justify-center mb-2">
                    <x-machine-illustration :status="$status" :name="$machine->name" :size="72" />
                </div>
                <div class="font-medium text-ink text-sm truncate">{{ $machine->name }}</div>
                <span class="inline-flex items-center font-mono text-[11px] font-semibold px-2 py-0.5 rounded-full mt-1 {{ $pillClass }}">{{ $statusLabel }}</span>
                @if ($current)
                    <div class="text-xs text-ink-faint mt-1 truncate">{{ $current->order_number }}</div>
                @endif
            </button>
        @empty
            <div class="col-span-full bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No washing machines yet — add one to get started.</div>
        @endforelse
    </div>

    @can('catalog.manage')
        <x-slide-panel name="machine-create" title="Add Machine" :error-fields="['name']">
            <form method="POST" action="{{ route('catalog.machines.store') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="machine_name" value="Name" />
                    <x-text-input id="machine_name" name="name" type="text" class="block w-full" value="{{ old('name') }}" placeholder="e.g. Machine 1" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Add machine</x-primary-button>
                    <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                </div>
            </form>
        </x-slide-panel>
    @endcan

    @foreach ($washingMachines as $machine)
        @php
            $current = $machine->orders->firstWhere('status', 'washing');
            $history = $machine->orders->reject(fn ($o) => $current && $o->id === $current->id)->take(8);
            $status = ! $machine->is_active ? 'retired' : ($current ? 'washing' : 'idle');
            $statusLabel = ['idle' => 'Idle', 'washing' => 'Washing', 'retired' => 'Retired'][$status];
            $pillClass = ['idle' => 'bg-success-soft text-success', 'washing' => 'bg-accent-soft text-accent-ink', 'retired' => 'bg-pill-bg text-pill-ink'][$status];
        @endphp
        <x-slide-panel name="machine-{{ $machine->id }}" title="{{ $machine->name }}">
            <div class="flex items-center gap-4 mb-5">
                <div class="flex-none">
                    <x-machine-illustration :status="$status" :name="$machine->name" :size="92" />
                </div>
                <div>
                    <div class="font-semibold text-ink">{{ $machine->name }}</div>
                    <span class="inline-flex items-center font-mono text-xs font-semibold px-2 py-0.5 rounded-full mt-1 {{ $pillClass }}">{{ $statusLabel }}</span>
                </div>
            </div>

            <div class="mb-5">
                <h4 class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-2">Current order</h4>
                @if ($current)
                    <a href="{{ route('orders.show', $current) }}" class="block bg-surface-2 rounded-lg px-3 py-2.5 hover:bg-accent-soft/40">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-ink text-sm">{{ $current->order_number }}</span>
                            <x-status-pill :status="$current->status" />
                        </div>
                        <div class="text-xs text-ink-muted mt-1">{{ $current->customer->full_name }}</div>
                    </a>
                @else
                    <p class="text-sm text-ink-faint">Not currently washing anything.</p>
                @endif
            </div>

            <div class="mb-5">
                <h4 class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-2">Recent wash history</h4>
                @forelse ($history as $order)
                    <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between px-3 py-2 border-b border-line last:border-0 hover:bg-surface-2 -mx-1 rounded">
                        <div>
                            <span class="text-sm text-ink">{{ $order->order_number }}</span>
                            <span class="text-xs text-ink-faint block">{{ $order->customer->full_name }}</span>
                        </div>
                        <span class="text-xs text-ink-faint">{{ $order->created_at->diffForHumans() }}</span>
                    </a>
                @empty
                    <p class="text-sm text-ink-faint">No past orders on this machine yet.</p>
                @endforelse
            </div>

            @can('catalog.manage')
                <div class="border-t border-line pt-4 space-y-3">
                    <form method="POST" action="{{ route('catalog.machines.update', $machine) }}" class="flex items-center gap-2">
                        @csrf @method('PUT')
                        <input type="text" name="name" value="{{ $machine->name }}" class="flex-1 min-w-0 bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                        <button type="submit" class="px-3 py-2 bg-accent text-white rounded-lg text-xs font-semibold hover:opacity-90">Rename</button>
                    </form>
                    <form method="POST" action="{{ route('catalog.machines.toggleActive', $machine) }}">
                        @csrf
                        <button type="submit" class="w-full text-center px-3 py-2 rounded-lg text-xs font-semibold {{ $machine->is_active ? 'bg-critical-soft text-critical hover:bg-critical hover:text-white' : 'bg-success-soft text-success hover:opacity-90' }} transition-colors">
                            {{ $machine->is_active ? 'Retire machine' : 'Activate machine' }}
                        </button>
                    </form>
                </div>
            @endcan
        </x-slide-panel>
    @endforeach
</x-app-layout>
