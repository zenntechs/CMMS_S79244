@extends('layouts.app')

@section('title', $spareItem->code)

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $spareItem->code }}</h1>
            @can('update', $spareItem)
                <a href="{{ route('spares.edit', $spareItem) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Edit</a>
            @endcan
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-700">Item details</h2>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Code</dt><dd>{{ $spareItem->code }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Description</dt><dd class="text-right">{{ $spareItem->description }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Unit</dt><dd>{{ $spareItem->unit }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Active</dt><dd>{{ $spareItem->is_active ? 'Yes' : 'No' }}</dd></div>
                </dl>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-700">Stock</h2>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">On hand</dt><dd>{{ number_format((float) $spareItem->quantity_on_hand, 2) }} {{ $spareItem->unit }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Reorder level</dt><dd>{{ number_format((float) $spareItem->reorder_level, 2) }} {{ $spareItem->unit }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd>
                        @if (! $spareItem->is_active)
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">Inactive</span>
                        @elseif ($spareItem->quantity_on_hand < $spareItem->reorder_level)
                            <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800">Below reorder</span>
                        @else
                            <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800">OK</span>
                        @endif
                    </dd></div>
                </dl>
                <p class="mt-3 text-xs text-slate-500">Stock balances change only through spare movements, not master-data edits.</p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Movement history</h2>
            @if ($spareItem->movements->isEmpty())
                <p class="text-sm text-slate-500">No movements recorded.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Occurred</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Type</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">Delta</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">Balance after</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Work order</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Actor</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($spareItem->movements as $movement)
                                <tr>
                                    <td class="px-4 py-3 text-slate-600">{{ $movement->occurred_at->format('Y-m-d H:i') }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $movement->movement_type->value }}</td>
                                    <td class="px-4 py-3 text-right text-slate-900">{{ number_format((float) $movement->quantity_delta, 2) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-900">{{ number_format((float) $movement->balance_after, 2) }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $movement->workOrder?->reference ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $movement->actor->name }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $movement->reason ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection