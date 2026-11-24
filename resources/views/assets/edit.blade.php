@extends('layouts.app')

@section('title', 'Edit asset')

@section('content')
    <div class="mx-auto max-w-2xl space-y-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Edit asset {{ $asset->code }}</h1>

        <form method="POST" action="{{ route('assets.update', $asset) }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            @include('assets._form', ['asset' => $asset])
            <div class="flex justify-end gap-3">
                <a href="{{ route('assets.show', $asset) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save changes</button>
            </div>
        </form>
    </div>
@endsection