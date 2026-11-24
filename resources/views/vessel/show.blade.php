@extends('layouts.app')

@section('title', 'Vessel')

@section('content')
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Vessel</h1>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Code</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $vessel->code }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Name</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $vessel->name }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Description</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $vessel->description }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Assets</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $vessel->assets_count }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-900">Assets</h2>
            </div>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Code</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Name</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Subsystem</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Criticality</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($vessel->assets as $asset)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                <a href="{{ route('assets.show', $asset) }}" class="hover:underline">{{ $asset->code }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $asset->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $asset->subsystem }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $asset->criticality->label() }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $asset->service_status->label() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection