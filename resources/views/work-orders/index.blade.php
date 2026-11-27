@extends('layouts.app')

@section('title', 'Work orders')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Work orders</h1>
                <p class="mt-1 text-sm text-slate-500">Each work order is one instance of a maintenance plan. At most one active work order may exist per plan.</p>
            </div>
            @can('create', App\Models\WorkOrder::class)
                <a href="{{ route('work-orders.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">New work order</a>
            @endcan
        </div>

        <form method="GET" action="{{ route('work-orders.index') }}" class="flex flex-wrap gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search reference / plan" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <select name="state" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All states</option>
                @foreach (['open','in_progress','submitted','closed'] as $s)
                    <option value="{{ $s }}" @selected(request('state') === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                @endforeach
            </select>
            <select name="assigned_to" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All technicians</option>
                @foreach ($technicians as $tech)
                    <option value="{{ $tech->id }}" @selected(request('assigned_to') === $tech->id)>{{ $tech->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filter</button>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Reference</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Plan / Asset</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Assigned to</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">State</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($workOrders as $wo)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                <a href="{{ route('work-orders.show', $wo) }}" class="hover:underline">{{ $wo->reference }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ $wo->plan->code }} — {{ $wo->plan->asset->code }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $wo->assignee?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">{{ $wo->state->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $wo->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No work orders found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $workOrders->links() }}
    </div>
@endsection