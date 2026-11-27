@extends('layouts.app')

@section('title', 'Audit event')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Audit event #{{ $event->id }}</h1>
            <a href="{{ route('audit.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Audit log</a>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Occurred</dt><dd class="mt-1 text-sm text-slate-900">{{ $event->occurred_at->format('Y-m-d H:i:s') }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Actor</dt><dd class="mt-1 text-sm text-slate-900">{{ $event->actor?->name ?? 'System' }} ({{ $event->actor?->email ?? '—' }})</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Action</dt><dd class="mt-1 text-sm text-slate-900">{{ $event->action }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Entity type</dt><dd class="mt-1 text-sm text-slate-900">{{ $event->entity_type }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Entity ID</dt><dd class="mt-1 text-sm text-slate-900">{{ $event->entity_id }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Reason</dt><dd class="mt-1 text-sm text-slate-900">{{ $event->reason ?? '—' }}</dd></div>
            </dl>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-700">Before</h2>
                <pre class="overflow-x-auto rounded-md bg-slate-50 p-3 text-xs text-slate-700">{{ $before }}</pre>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-700">After</h2>
                <pre class="overflow-x-auto rounded-md bg-slate-50 p-3 text-xs text-slate-700">{{ $after }}</pre>
            </div>
        </div>
    </div>
@endsection