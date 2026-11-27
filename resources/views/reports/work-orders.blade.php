@extends('layouts.app')

@section('title', 'Work-order report')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Work-order report</h1>
            <a href="{{ route('reports.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Reports</a>
        </div>

        <form method="GET" action="{{ route('reports.work-orders') }}" class="flex flex-wrap gap-3">
            <select name="state" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All states</option>
                @foreach (['open','in_progress','submitted','closed'] as $state)
                    <option value="{{ $state }}" @selected(($filters['state'] ?? '') === $state)>{{ ucfirst(str_replace('_', ' ', $state)) }}</option>
                @endforeach
            </select>
            @if (! auth()->user()->isTechnician())
                <select name="assigned_to" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All technicians</option>
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->id }}" @selected(($filters['assigned_to'] ?? '') === $technician->id)>{{ $technician->name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="asset_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All assets</option>
                @foreach ($assets as $asset)
                    <option value="{{ $asset->id }}" @selected(($filters['asset_id'] ?? '') === $asset->id)>{{ $asset->code }}</option>
                @endforeach
            </select>
            <select name="plan_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All plans</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->id }}" @selected(($filters['plan_id'] ?? '') === $plan->id)>{{ $plan->code }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filter</button>
            <a href="{{ route('reports.work-orders') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Reference</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Plan / Asset</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Assigned to</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">State</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Created</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Submitted</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Closed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($workOrders as $workOrder)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                <a href="{{ route('work-orders.show', $workOrder) }}" class="hover:underline">{{ $workOrder->reference }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $workOrder->plan->code }} — {{ $workOrder->plan->asset->code }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $workOrder->assignee?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">{{ $workOrder->state->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $workOrder->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $workOrder->submitted_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $workOrder->closed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No work orders match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $workOrders->links() }}
    </div>
@endsection