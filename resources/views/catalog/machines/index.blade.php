<x-app-layout>
    <x-slot name="header">Washing Machines</x-slot>

    <div class="bg-surface border border-line rounded-2xl p-6 max-w-xl">
        <p class="text-sm text-ink-muted mb-4">How many orders can wash at once — one per active machine. Retire a machine (rather than deleting it) if it breaks down; its history stays intact and it stops being offered until reactivated.</p>

        <div class="space-y-2 mb-4">
            @forelse ($washingMachines as $machine)
                <div class="flex items-center justify-between gap-2 bg-surface-2 rounded-lg px-3 py-2">
                    @can('catalog.manage')
                        <form method="POST" action="{{ route('catalog.machines.update', $machine) }}" class="flex-1 min-w-0">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ $machine->name }}" onchange="this.form.submit()" class="w-full bg-transparent border-0 focus:ring-1 focus:ring-accent rounded text-sm text-ink px-1.5 py-1 {{ $machine->is_active ? '' : 'text-ink-faint line-through' }}">
                        </form>
                    @else
                        <span class="flex-1 min-w-0 text-sm text-ink px-1.5 py-1 {{ $machine->is_active ? '' : 'text-ink-faint line-through' }}">{{ $machine->name }}</span>
                    @endcan
                    <div class="flex items-center gap-2 flex-none">
                        <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded-full {{ $machine->is_active ? 'bg-success-soft text-success' : 'bg-pill-bg text-pill-ink' }}">
                            {{ $machine->is_active ? 'Active' : 'Retired' }}
                        </span>
                        @can('catalog.manage')
                            <form method="POST" action="{{ route('catalog.machines.toggleActive', $machine) }}">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-accent-ink hover:underline">
                                    {{ $machine->is_active ? 'Retire' : 'Activate' }}
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="text-sm text-ink-faint">No washing machines yet — add one below.</p>
            @endforelse
        </div>

        @can('catalog.manage')
            <form method="POST" action="{{ route('catalog.machines.store') }}" class="flex gap-2">
                @csrf
                <input type="text" name="name" placeholder="e.g. Machine 1" class="flex-1 min-w-0 bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                <button type="submit" class="px-4 py-2 bg-accent text-white rounded-lg text-sm font-semibold whitespace-nowrap hover:opacity-90 transition-opacity">Add Machine</button>
            </form>
            <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
        @endcan
    </div>
</x-app-layout>
