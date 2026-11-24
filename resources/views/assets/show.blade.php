@extends('layouts.app')

@section('title', $asset->code)

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $asset->code }} — {{ $asset->name }}</h1>
            @can('update', $asset)
                <a href="{{ route('assets.edit', $asset) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Edit</a>
            @endcan
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Vessel</dt><dd class="mt-1 text-sm text-slate-900">{{ $asset->vessel->name }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Subsystem</dt><dd class="mt-1 text-sm text-slate-900">{{ $asset->subsystem }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Location</dt><dd class="mt-1 text-sm text-slate-900">{{ $asset->location ?? '—' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Criticality</dt><dd class="mt-1 text-sm text-slate-900">{{ $asset->criticality->label() }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Service status</dt><dd class="mt-1 text-sm text-slate-900">{{ $asset->service_status->label() }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Hour meter</dt><dd class="mt-1 text-sm text-slate-900">{{ $asset->has_hour_meter ? 'Yes ('.$asset->meter_unit.')' : 'No' }}</dd></div>
            </dl>
        </div>

        @if ($asset->maintenancePlans->isNotEmpty())
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-900">Maintenance plans</h2></div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($asset->maintenancePlans as $plan)
                        <li class="px-4 py-3 text-sm">
                            <a href="{{ route('plans.show', $plan) }}" class="font-medium text-slate-900 hover:underline">{{ $plan->code }}</a>
                            <span class="ml-2 text-slate-500">{{ $plan->trigger_type->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection