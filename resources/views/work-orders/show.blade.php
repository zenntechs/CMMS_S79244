@extends('layouts.app')

@section('title', $workOrder->reference)

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $workOrder->reference }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700">{{ $workOrder->state->label() }}</span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-700">Plan &amp; asset</h2>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Plan</dt><dd><a href="{{ route('plans.show', $workOrder->plan) }}" class="hover:underline">{{ $workOrder->plan->code }}</a></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Task</dt><dd class="text-right">{{ $workOrder->plan->task_description }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Asset</dt><dd><a href="{{ route('assets.show', $workOrder->plan->asset) }}" class="hover:underline">{{ $workOrder->plan->asset->code }}</a></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Trigger</dt><dd>{{ $workOrder->plan->trigger_type->label() }}</dd></div>
                </dl>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-700">Assignment</h2>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Created by</dt><dd>{{ $workOrder->createdBy->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Assigned to</dt><dd>{{ $workOrder->assignee?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Created at</dt><dd>{{ $workOrder->created_at->format('Y-m-d H:i') }}</dd></div>
                    @if ($workOrder->started_at)
                        <div class="flex justify-between"><dt class="text-slate-500">Started at</dt><dd>{{ $workOrder->started_at->format('Y-m-d H:i') }}</dd></div>
                    @endif
                    @if ($workOrder->submitted_at)
                        <div class="flex justify-between"><dt class="text-slate-500">Submitted at</dt><dd>{{ $workOrder->submitted_at->format('Y-m-d H:i') }}</dd></div>
                    @endif
                    @if ($workOrder->closed_at)
                        <div class="flex justify-between"><dt class="text-slate-500">Closed at</dt><dd>{{ $workOrder->closed_at->format('Y-m-d H:i') }}</dd></div>
                    @endif
                    @if ($workOrder->actual_completion_hours !== null)
                        <div class="flex justify-between"><dt class="text-slate-500">Actual hours</dt><dd>{{ number_format((float) $workOrder->actual_completion_hours, 2) }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Event history</h2>
            @if ($workOrder->events->isEmpty())
                <p class="text-sm text-slate-500">No events recorded.</p>
            @else
                <ol class="space-y-2 text-sm">
                    @foreach ($workOrder->events->sortBy('occurred_at') as $event)
                        <li class="flex flex-wrap gap-2">
                            <span class="font-medium text-slate-900">{{ $event->event_type->value }}</span>
                            <span class="text-slate-500">{{ $event->occurred_at->format('Y-m-d H:i') }}</span>
                            <span class="text-slate-600">by {{ $event->actor->name }}</span>
                            @if ($event->from_state || $event->to_state)
                                <span class="text-slate-500">({{ $event->from_state?->value ?? '—' }} → {{ $event->to_state?->value ?? '—' }})</span>
                            @endif
                            @if ($event->reason)
                                <span class="text-slate-600">— {{ $event->reason }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>
@endsection