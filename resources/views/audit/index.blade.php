<x-app-layout>
    <x-slot name="header">Audit Log</x-slot>

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
        <div>
            <label class="block text-xs text-ink-muted mb-1">Subject</label>
            <select name="subject_type" onchange="this.form.submit()" class="bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                <option value="">All subjects</option>
                @foreach ($subjectTypes as $type)
                    <option value="{{ $type }}" @selected(request('subject_type') === $type)>{{ class_basename($type) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-ink-muted mb-1">User</label>
            <select name="causer_id" onchange="this.form.submit()" class="bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                <option value="">All users</option>
                @foreach ($causers as $causer)
                    <option value="{{ $causer->id }}" @selected((string) request('causer_id') === (string) $causer->id)>{{ $causer->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-ink-muted mb-1">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="bg-surface border-line-strong rounded-lg shadow-sm text-sm">
        </div>
        <div>
            <label class="block text-xs text-ink-muted mb-1">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="bg-surface border-line-strong rounded-lg shadow-sm text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-surface-2 text-ink rounded-lg text-sm font-semibold">Filter</button>
        @if (request()->anyFilled(['subject_type', 'causer_id', 'from', 'to']))
            <a href="{{ route('audit.index') }}" class="text-sm text-ink-muted hover:text-ink">Clear</a>
        @endif
    </form>

    <div class="bg-surface border border-line rounded-2xl overflow-hidden hidden md:block">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-2">
                    <tr>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Date</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Action</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Subject</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">User</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-t border-line hover:bg-surface-2">
                            <td class="px-4 py-3 font-mono text-xs text-ink-faint">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-ink">{{ ucfirst($log->description) }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-ink-muted">{{ $log->subjectLabel() }}</td>
                            <td class="px-4 py-3 text-ink-muted">{{ $log->causer->name ?? 'System' }}</td>
                            <td class="px-4 py-3">
                                @if ($log->properties)
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($log->properties as $key => $value)
                                            <span class="inline-flex items-center bg-pill-bg text-pill-ink font-mono text-xs px-2 py-0.5 rounded-full">
                                                {{ $key }}: {{ is_bool($value) ? ($value ? 'true' : 'false') : $value }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-ink-faint">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-ink-faint text-sm">No activity recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="md:hidden space-y-3">
        @forelse ($logs as $log)
            <div class="bg-surface border border-line rounded-2xl p-4">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-ink font-medium">{{ ucfirst($log->description) }}</span>
                    <span class="font-mono text-xs text-ink-faint">{{ $log->created_at->format('Y-m-d H:i') }}</span>
                </div>
                <div class="flex items-center justify-between text-sm text-ink-muted mb-2">
                    <span class="font-mono text-xs">{{ $log->subjectLabel() }}</span>
                    <span>{{ $log->causer->name ?? 'System' }}</span>
                </div>
                @if ($log->properties)
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($log->properties as $key => $value)
                            <span class="inline-flex items-center bg-pill-bg text-pill-ink font-mono text-xs px-2 py-0.5 rounded-full">
                                {{ $key }}: {{ is_bool($value) ? ($value ? 'true' : 'false') : $value }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No activity recorded yet.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-app-layout>
