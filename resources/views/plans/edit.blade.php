@extends('layouts.app')

@section('title', 'Edit maintenance plan')

@section('content')
    <div class="mx-auto max-w-2xl space-y-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Edit maintenance plan {{ $plan->code }}</h1>

        <form method="POST" action="{{ route('plans.update', $plan) }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            @include('plans._form', ['plan' => $plan])
            <div class="flex justify-end gap-3">
                <a href="{{ route('plans.show', $plan) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save changes</button>
            </div>
        </form>
    </div>
@endsection