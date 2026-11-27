@extends('layouts.app')

@section('title', 'Maintenance due report')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Maintenance due report</h1>
            <a href="{{ route('reports.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Reports</a>
        </div>

        <form method="GET" action="{{ route('reports.maintenance') }}" class="flex flex-wrap gap-3">
            <select name="asset_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All assets</option>
                @foreach (\App\Models\Asset::orderBy('code')->get() as $asset)
                    <option value="{{ $asset->id }}" @selected(($filters['asset_id'] ?? '') === $asset->id)>{{ $asset->code }}</option>
                @endforeach
            </select>
            <select name="subsystem" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All subsystems</option>
                @foreach ($subsystems as $subsystem)
                    <option value="{{ $subsystem }}" @selected(($filters['subsystem'] ?? '') === $subsystem)>{{ $subsystem }}</option>
                @endforeach
            </select>
            <select name="criticality" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All criticalities</option>
                @foreach (['critical','high','medium','low'] as $criticality)
                    <option value="{{ $criticality }}" @selected(($filters['criticality'] ?? '') === $criticality)>{{ ucfirst($criticality) }}</option>
                @endforeach
            </select>
            <select name="trigger_type" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All triggers</option>
                @foreach (['calendar','hours','dual'] as $trigger)
                    <option value="{{ $trigger }}" @selected(($filters['trigger_type'] ?? '') === $trigger)>{{ ucfirst($trigger) }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All statuses</option>
                @foreach (['not_due','due','overdue','incomplete'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
            <input type="date" name="due_from" value="{{ $filters['due_from'] ?? '' }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <input type="date" name="due_to" value="{{ $filters['due_to'] ?? '' }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filter</button>
            <a href="{{ route('reports.maintenance') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Plan</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Asset</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Trigger</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Due date</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Hours due at</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Latest reading</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                <a href="{{ route('plans.show', $row['plan']) }}" class="hover:underline">{{ $row['plan']->code }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $row['asset']->code }} — {{ $row['asset']->subsystem }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $row['plan']->trigger_type->label() }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">{{ $row['status']->state->label() }} ({{ $row['status']->trigger->label() }})</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $row['status']->dueDate?->toDateString() ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $row['status']->hoursDueAt ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $row['plan']->asset->latestMeterReading?->reading_hours !== null ? number_format((float) $row['plan']->asset->latestMeterReading->reading_hours, 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No maintenance plans match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $rows->links() }}
    </div>
@endsection