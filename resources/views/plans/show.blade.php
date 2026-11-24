@extends('layouts.app')

@section('title', $plan->code)

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $plan->code }}</h1>
            @can('update', $plan)
                <a href="{{ route('plans.edit', $plan) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Edit</a>
            @endcan
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Asset</dt><dd class="mt-1 text-sm text-slate-900">{{ $plan->asset->code }} — {{ $plan->asset->name }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Trigger</dt><dd class="mt-1 text-sm text-slate-900">{{ $plan->trigger_type->label() }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Task</dt><dd class="mt-1 text-sm text-slate-900">{{ $plan->task_description }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Interval (days)</dt><dd class="mt-1 text-sm text-slate-900">{{ $plan->interval_days ?? '—' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Interval (hours)</dt><dd class="mt-1 text-sm text-slate-900">{{ $plan->interval_hours !== null ? number_format((float) $plan->interval_hours, 2) : '—' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Last completion date</dt><dd class="mt-1 text-sm text-slate-900">{{ $plan->last_completion_date?->toDateString() ?? '—' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Last completion hours</dt><dd class="mt-1 text-sm text-slate-900">{{ $plan->last_completion_hours !== null ? number_format((float) $plan->last_completion_hours, 2) : '—' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Source label</dt><dd class="mt-1 text-sm text-slate-900">{{ $plan->source_label }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Active</dt><dd class="mt-1 text-sm text-slate-900">{{ $plan->is_active ? 'Yes' : 'No' }}</dd></div>
            </dl>
        </div>

        @can('activate', $plan)
            <form method="POST" action="{{ $plan->is_active ? route('plans.deactivate', $plan) : route('plans.activate', $plan) }}">
                @csrf
                @if ($plan->is_active) @method('DELETE') @endif
                <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    {{ $plan->is_active ? 'Deactivate plan' : 'Activate plan' }}
                </button>
            </form>
        @endcan

        @if ($plan->workOrders->isNotEmpty())
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-900">Work orders</h2></div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($plan->workOrders as $wo)
                        <li class="px-4 py-3 text-sm text-slate-700">{{ $wo->reference }} — {{ $wo->state->label() }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection