@extends('layouts.app')

@section('title', 'New asset')

@section('content')
    <div class="mx-auto max-w-2xl space-y-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">New asset</h1>

        <form method="POST" action="{{ route('assets.store') }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @include('assets._form', ['asset' => null])
            <div class="flex justify-end gap-3">
                <a href="{{ route('assets.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Create asset</button>
            </div>
        </form>
    </div>
@endsection