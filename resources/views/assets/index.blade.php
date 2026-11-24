@extends('layouts.app')

@section('title', 'Assets')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Assets</h1>
            @can('create', App\Models\Asset::class)
                <a href="{{ route('assets.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">New asset</a>
            @endcan
        </div>

        <form method="GET" action="{{ route('assets.index') }}" class="flex flex-wrap gap-3">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search code, name, subsystem"
                   class="w-64 rounded-md border border-slate-300 px-3 py-2 text-sm">
            <select name="subsystem" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All subsystems</option>
                @foreach ($subsystems as $s)
                    <option value="{{ $s }}" @selected(request('subsystem') === $s)>{{ $s }}</option>
                @endforeach
            </select>
            <select name="service_status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All statuses</option>
                <option value="in_service" @selected(request('service_status') === 'in_service')>In service</option>
                <option value="standby" @selected(request('service_status') === 'standby')>Standby</option>
                <option value="withdrawn" @selected(request('service_status') === 'withdrawn')>Withdrawn</option>
            </select>
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filter</button>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Code</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Name</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Subsystem</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Criticality</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Hour meter</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assets as $asset)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                <a href="{{ route('assets.show', $asset) }}" class="hover:underline">{{ $asset->code }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $asset->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $asset->subsystem }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $asset->criticality->label() }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $asset->service_status->label() }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $asset->has_hour_meter ? 'Yes' : 'No' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No assets found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $assets->links() }}
    </div>
@endsection