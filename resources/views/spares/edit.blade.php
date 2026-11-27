@extends('layouts.app')

@section('title', 'Edit '.$spareItem->code)

@section('content')
    <div class="mx-auto max-w-2xl space-y-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Edit {{ $spareItem->code }}</h1>

        <form method="POST" action="{{ route('spares.update', $spareItem) }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label for="code" class="block text-sm font-medium text-slate-700">Code</label>
                <input type="text" name="code" id="code" value="{{ old('code', $spareItem->code) }}" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-slate-700">Description</label>
                <input type="text" name="description" id="description" value="{{ old('description', $spareItem->description) }}" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label for="unit" class="block text-sm font-medium text-slate-700">Unit</label>
                <input type="text" name="unit" id="unit" value="{{ old('unit', $spareItem->unit) }}" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Quantity on hand</label>
                <p class="mt-1 text-sm text-slate-600">{{ number_format((float) $spareItem->quantity_on_hand, 2) }} {{ $spareItem->unit }} (read-only)</p>
            </div>

            <div>
                <label for="reorder_level" class="block text-sm font-medium text-slate-700">Reorder level</label>
                <input type="number" step="0.01" min="0" name="reorder_level" id="reorder_level" value="{{ old('reorder_level', $spareItem->reorder_level) }}" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $spareItem->is_active)) class="rounded border-slate-300">
                <label for="is_active" class="text-sm text-slate-700">Active</label>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save changes</button>
                <a href="{{ route('spares.show', $spareItem) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </div>
@endsection