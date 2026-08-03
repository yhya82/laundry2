<x-app-layout>
    <x-slot name="header">Damage Management</x-slot>

    @if (session('status'))
        <div class="mb-4 text-sm text-success bg-success-soft border border-success/30 rounded-lg px-4 py-2.5">{{ session('status') }}</div>
    @endif

    <form method="GET" class="mb-5 max-w-xs">
        <select name="status" onchange="this.form.submit()" class="w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
            <option value="">All statuses</option>
            @foreach (['pending_review', 'under_investigation', 'approved', 'rejected', 'resolved', 'closed'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
            @endforeach
        </select>
    </form>

    <div class="bg-surface border border-line rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Reported</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Order</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Customer</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Type</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($damageRecords as $damage)
                    <tr class="border-t border-line hover:bg-surface-2">
                        <td class="px-4 py-3 font-mono text-xs text-ink-faint">{{ $damage->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('damage.show', $damage) }}" class="font-mono text-accent-ink hover:underline">{{ $damage->order->order_number }}</a>
                        </td>
                        <td class="px-4 py-3 text-ink">{{ $damage->order->customer->full_name }}</td>
                        <td class="px-4 py-3 text-ink-muted">{{ $damage->damageType->name }}</td>
                        <td class="px-4 py-3"><x-status-pill :status="$damage->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-ink-faint text-sm">No damage reports.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $damageRecords->links() }}</div>
</x-app-layout>
