@extends('layouts.app')

@section('title', 'Spare-parts inventory report')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Spare-parts inventory report</h1>
            <a href="{{ route('reports.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Reports</a>
        </div>

        <form method="GET" action="{{ route('reports.spares') }}" class="flex flex-wrap gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search code / description / unit" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <select name="status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All stock statuses</option>
                <option value="low" @selected(($filters['status'] ?? '') === 'low')>Below reorder</option>
                <option value="ok" @selected(($filters['status'] ?? '') === 'ok')>OK</option>
            </select>
            <select name="active" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All items</option>
                <option value="active" @selected(($filters['active'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['active'] ?? '') === 'inactive')>Inactive</option>
            </select>
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filter</button>
            <a href="{{ route('reports.spares') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
            <a href="{{ route('reports.spares.export', request()->query()) }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Export CSV</a>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Code</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Description</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Unit</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-700">On hand</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-700">Reorder level</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($items as $item)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                <a href="{{ route('spares.show', $item) }}" class="hover:underline">{{ $item->code }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $item->description }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $item->unit }}</td>
                            <td class="px-4 py-3 text-right text-slate-900">{{ number_format((float) $item->quantity_on_hand, 2) }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ number_format((float) $item->reorder_level, 2) }}</td>
                            <td class="px-4 py-3">
                                @if (! $item->is_active)
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">Inactive</span>
                                @elseif ($item->quantity_on_hand < $item->reorder_level)
                                    <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800">Below reorder</span>
                                @else
                                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800">OK</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $item->is_active ? 'Yes' : 'No' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No spare items match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $items->links() }}
    </div>
@endsection