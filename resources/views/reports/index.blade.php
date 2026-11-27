@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Reports</h1>
            <p class="mt-1 text-sm text-slate-600">Read-only reporting views for maintenance, work orders and spare parts.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <a href="{{ route('reports.maintenance') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:bg-slate-50">
                <h2 class="text-sm font-semibold text-slate-900">Maintenance due report</h2>
                <p class="mt-1 text-sm text-slate-600">Plan status, next-due dates and incomplete scheduling data.</p>
            </a>
            <a href="{{ route('reports.work-orders') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:bg-slate-50">
                <h2 class="text-sm font-semibold text-slate-900">Work-order report</h2>
                <p class="mt-1 text-sm text-slate-600">Status, assignment, dates and completion details.</p>
            </a>
            <a href="{{ route('reports.spares') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:bg-slate-50">
                <h2 class="text-sm font-semibold text-slate-900">Spare-parts inventory report</h2>
                <p class="mt-1 text-sm text-slate-600">Current stock, reorder levels and inventory status.</p>
            </a>
            <a href="{{ route('reports.movements') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:bg-slate-50">
                <h2 class="text-sm font-semibold text-slate-900">Spare-parts movement report</h2>
                <p class="mt-1 text-sm text-slate-600">Movement history, actors, quantities and balances.</p>
            </a>
        </div>
    </div>
@endsection