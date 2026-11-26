@extends('layouts.app')

@section('title', 'Meter readings — '.$asset->code)

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Meter readings — {{ $asset->code }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ $asset->name }} · {{ $asset->service_status->label() }}</p>
            </div>
            <a href="{{ route('assets.show', $asset) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to asset</a>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <dl class="grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Current hours</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">
                        {{ $latest ? number_format((float) $latest->reading_hours, 2) : '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Last accepted reading</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $latest?->reading_at?->toDateTimeString() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Hour meter</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $asset->has_hour_meter ? 'Yes ('.$asset->meter_unit.')' : 'No' }}</dd>
                </div>
            </dl>
        </div>

        @if (! $asset->has_hour_meter)
            <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">
                This asset has no running-hour meter, so readings do not apply.
            </div>
        @else
            @can('recordReading', $asset)
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-900">Record a reading</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Cumulative hours must be equal to or greater than the highest accepted reading; future timestamps are rejected.
                    </p>
                    <div class="mt-4">
                        @include('readings._form')
                    </div>
                </div>
            @endcan
        @endif

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Reading at</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Running hours</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Recorded by</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($readings as $reading)
                        <tr>
                            <td class="px-4 py-3 text-slate-700">{{ $reading->reading_at->toDateTimeString() }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ number_format((float) $reading->reading_hours, 2) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $reading->recordedBy?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $reading->note ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No readings recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $readings->links() }}
    </div>
@endsection
