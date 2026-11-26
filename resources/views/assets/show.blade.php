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

        @if ($asset->has_hour_meter)
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">Running-hour meter</h2>
                    <a href="{{ route('assets.readings.index', $asset) }}" class="text-sm font-medium text-slate-700 hover:underline">Full history</a>
                </div>
                <div class="space-y-4 px-4 py-4">
                    <dl class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Current hours</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ $asset->latestMeterReading ? number_format((float) $asset->latestMeterReading->reading_hours, 2) : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Last accepted reading</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ $asset->latestMeterReading?->localReadingAt()->toDateTimeString() ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Service status</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ $asset->service_status->label() }}</dd>
                        </div>
                    </dl>

                    @if ($recentReadings->isNotEmpty())
                        <ul class="divide-y divide-slate-100 rounded-md border border-slate-200">
                            @foreach ($recentReadings as $reading)
                                <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
                                    <span class="font-medium text-slate-900">{{ number_format((float) $reading->reading_hours, 2) }} h</span>
                                    <span class="text-slate-600">{{ $reading->localReadingAt()->toDateTimeString() }}</span>
                                    <span class="text-slate-500">{{ $reading->recordedBy?->name ?? '—' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @can('recordReading', $asset)
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                            <h3 class="text-sm font-semibold text-slate-900">Record a reading</h3>
                            <div class="mt-3">
                                @include('readings._form')
                            </div>
                        </div>
                    @endcan
                </div>
            </div>
        @endif

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