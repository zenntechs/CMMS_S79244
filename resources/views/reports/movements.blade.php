@extends('layouts.app')

@section('title', 'Spare-parts movement report')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Spare-parts movement report</h1>
            <a href="{{ route('reports.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Reports</a>
        </div>

        <form method="GET" action="{{ route('reports.movements') }}" class="flex flex-wrap gap-3">
            <select name="spare_item_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All spare items</option>
                @foreach ($spareItems as $spareItem)
                    <option value="{{ $spareItem->id }}" @selected(($filters['spare_item_id'] ?? '') === $spareItem->id)>{{ $spareItem->code }}</option>
                @endforeach
            </select>
            <select name="movement_type" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All movement types</option>
                @foreach (['issue','return','receipt','adjustment'] as $movementType)
                    <option value="{{ $movementType }}" @selected(($filters['movement_type'] ?? '') === $movementType)>{{ ucfirst($movementType) }}</option>
                @endforeach
            </select>
            @if (! auth()->user()->isTechnician())
                <select name="work_order_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All work orders</option>
                    @foreach ($workOrders as $workOrder)
                        <option value="{{ $workOrder->id }}" @selected(($filters['work_order_id'] ?? '') === $workOrder->id)>{{ $workOrder->reference }}</option>
                    @endforeach
                </select>
                <select name="actor_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All actors</option>
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->id }}" @selected(($filters['actor_id'] ?? '') === $technician->id)>{{ $technician->name }}</option>
                    @endforeach
                </select>
            @endif
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filter</button>
            <a href="{{ route('reports.movements') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Occurred</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Type</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-700">Quantity</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-700">Balance after</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Work order</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Actor</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Reason</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="px-4 py-3 text-slate-600">{{ $movement->occurred_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                <a href="{{ route('spares.show', $movement->spareItem) }}" class="hover:underline">{{ $movement->spareItem->code }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $movement->movement_type->value }}</td>
                            <td class="px-4 py-3 text-right text-slate-900">{{ number_format((float) $movement->quantity_delta, 2) }}</td>
                            <td class="px-4 py-3 text-right text-slate-900">{{ number_format((float) $movement->balance_after, 2) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $movement->workOrder?->reference ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $movement->actor->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $movement->reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-slate-500">No spare movements match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $movements->links() }}
    </div>
@endsection