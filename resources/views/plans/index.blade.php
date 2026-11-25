@extends('layouts.app')

@section('title', 'Maintenance plans')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Maintenance plans</h1>
            @can('create', App\Models\MaintenancePlan::class)
                <a href="{{ route('plans.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">New plan</a>
            @endcan
        </div>

        <form method="GET" action="{{ route('plans.index') }}" class="flex flex-wrap gap-3">
            <select name="trigger_type" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All triggers</option>
                <option value="calendar" @selected(request('trigger_type') === 'calendar')>Calendar</option>
                <option value="hours" @selected(request('trigger_type') === 'hours')>Running hours</option>
                <option value="dual" @selected(request('trigger_type') === 'dual')>Dual</option>
            </select>
            <select name="is_active" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="1" @selected(request('is_active') === '1')>Active</option>
                <option value="0" @selected(request('is_active') === '0')>Inactive</option>
            </select>
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filter</button>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Code</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Asset</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Trigger</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Interval</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($plans as $plan)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                <a href="{{ route('plans.show', $plan) }}" class="hover:underline">{{ $plan->code }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $plan->asset->code }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $plan->trigger_type->label() }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                @if ($plan->usesCalendar()) {{ $plan->interval_days }} d @endif
                                @if ($plan->usesHours()) {{ number_format((float) $plan->interval_hours, 2) }} h @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $dueStatuses[$plan->id]->state->label() }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $plan->is_active ? 'Yes' : 'No' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No plans found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $plans->links() }}
    </div>
@endsection