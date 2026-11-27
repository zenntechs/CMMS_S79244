@extends('layouts.app')

@section('title', 'Audit log')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Audit log</h1>
        </div>

        <form method="GET" action="{{ route('audit.index') }}" class="flex flex-wrap gap-3">
            <select name="action" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All actions</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                @endforeach
            </select>
            <select name="actor_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All actors</option>
                @foreach ($actors as $actor)
                    <option value="{{ $actor->id }}" @selected(($filters['actor_id'] ?? '') === $actor->id)>{{ $actor->name }}</option>
                @endforeach
            </select>
            <select name="entity_type" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All entity types</option>
                @foreach ($entityTypes as $entityType)
                    <option value="{{ $entityType }}" @selected(($filters['entity_type'] ?? '') === $entityType)>{{ $entityType }}</option>
                @endforeach
            </select>
            <input type="text" name="entity_id" value="{{ $filters['entity_id'] ?? '' }}" placeholder="Entity ID" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filter</button>
            <a href="{{ route('audit.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Occurred</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Actor</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Action</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Entity type</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Entity ID</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Reason</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($events as $event)
                        <tr>
                            <td class="px-4 py-3 text-slate-600">{{ $event->occurred_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $event->actor?->name ?? 'System' }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                <a href="{{ route('audit.show', $event) }}" class="hover:underline">{{ $event->action }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $event->entity_type }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $event->entity_id }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $event->reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No audit events match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $events->links() }}
    </div>
@endsection