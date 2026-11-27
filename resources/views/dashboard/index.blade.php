@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Operational dashboard</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Signed in as {{ $user->name }} · {{ $user->role->label() }}
                    @if ($vessel)
                        · {{ $vessel->name }} ({{ $vessel->code }})
                    @endif
                </p>
            </div>
            <p class="text-xs text-slate-500">Maintenance status as of {{ now()->format('Y-m-d H:i') }}</p>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Assets</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $assetCount }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ $activeAssetCount }} in service · {{ $withdrawnAssetCount }} withdrawn</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Active plans</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $planCount }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ $maintenanceCounts['overdue'] }} overdue · {{ $maintenanceCounts['due'] }} due</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Active work orders</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $workOrderCounts['active'] }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ $workOrderCounts['open'] }} open · {{ $workOrderCounts['in_progress'] }} in progress · {{ $workOrderCounts['submitted'] }} submitted</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Spare items</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $spareItemCount }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ $lowStockCount }} below reorder · {{ $spareItemCount }} active item types</p>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">Maintenance status</h2>
                @php
                    $maintenanceTotal = max(1, $maintenanceCounts['not_due'] + $maintenanceCounts['due'] + $maintenanceCounts['overdue'] + $maintenanceCounts['incomplete']);
                @endphp
                <div class="mt-4 space-y-3">
                    @foreach ([
                        ['label' => 'Not due', 'count' => $maintenanceCounts['not_due'], 'class' => 'bg-emerald-500'],
                        ['label' => 'Due', 'count' => $maintenanceCounts['due'], 'class' => 'bg-amber-500'],
                        ['label' => 'Overdue', 'count' => $maintenanceCounts['overdue'], 'class' => 'bg-rose-500'],
                        ['label' => 'Incomplete', 'count' => $maintenanceCounts['incomplete'], 'class' => 'bg-slate-400'],
                    ] as $row)
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-700">{{ $row['label'] }}</span>
                                <span class="font-medium text-slate-900">{{ $row['count'] }}</span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-slate-100">
                                <div class="{{ $row['class'] }} h-2 rounded-full" style="width: {{ round(($row['count'] / $maintenanceTotal) * 100, 1) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">Work-order status</h2>
                @php
                    $workOrderTotal = max(1, $workOrderCounts['open'] + $workOrderCounts['in_progress'] + $workOrderCounts['submitted'] + $workOrderCounts['closed']);
                @endphp
                <div class="mt-4 space-y-3">
                    @foreach ([
                        ['label' => 'Open', 'count' => $workOrderCounts['open'], 'class' => 'bg-sky-500'],
                        ['label' => 'In progress', 'count' => $workOrderCounts['in_progress'], 'class' => 'bg-indigo-500'],
                        ['label' => 'Submitted', 'count' => $workOrderCounts['submitted'], 'class' => 'bg-amber-500'],
                        ['label' => 'Closed', 'count' => $workOrderCounts['closed'], 'class' => 'bg-emerald-500'],
                    ] as $row)
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-700">{{ $row['label'] }}</span>
                                <span class="font-medium text-slate-900">{{ $row['count'] }}</span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-slate-100">
                                <div class="{{ $row['class'] }} h-2 rounded-full" style="width: {{ round(($row['count'] / $workOrderTotal) * 100, 1) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900">Maintenance requiring attention</h2>
                    <a href="{{ route('plans.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">View plans</a>
                </div>
                @if ($attentionPlans->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">No maintenance plans require attention.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">Plan</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">Asset</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">Status</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">Trigger</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($attentionPlans as $row)
                                    <tr>
                                        <td class="px-3 py-2 font-medium text-slate-900">
                                            <a href="{{ route('plans.show', $row['plan']) }}" class="hover:underline">{{ $row['plan']->code }}</a>
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">{{ $row['plan']->asset->code }}</td>
                                        <td class="px-3 py-2">
                                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">{{ $row['status']->state->label() }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">{{ $row['status']->trigger->label() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900">Low-stock inventory</h2>
                    <a href="{{ route('spares.index', ['status' => 'low']) }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">View spares</a>
                </div>
                @if ($lowStockItems->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">No active spare items are below reorder level.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">Item</th>
                                    <th class="px-3 py-2 text-right font-semibold text-slate-700">On hand</th>
                                    <th class="px-3 py-2 text-right font-semibold text-slate-700">Reorder</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($lowStockItems as $item)
                                    <tr>
                                        <td class="px-3 py-2 font-medium text-slate-900">
                                            <a href="{{ route('spares.show', $item) }}" class="hover:underline">{{ $item->code }}</a>
                                        </td>
                                        <td class="px-3 py-2 text-right text-slate-900">{{ number_format((float) $item->quantity_on_hand, 2) }} {{ $item->unit }}</td>
                                        <td class="px-3 py-2 text-right text-slate-600">{{ number_format((float) $item->reorder_level, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Shortcuts</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('assets.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Assets</a>
                <a href="{{ route('plans.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Plans</a>
                <a href="{{ route('work-orders.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Work orders</a>
                <a href="{{ route('spares.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Spares</a>
                @if ($user->isChiefEngineer())
                    <a href="{{ route('work-orders.create') }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">New work order</a>
                    <a href="{{ route('spares.create') }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">New spare item</a>
                @endif
                @if ($user->isTechnician())
                    <a href="{{ route('work-orders.index') }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">My work orders</a>
                @endif
            </div>
        </section>
    </div>
@endsection