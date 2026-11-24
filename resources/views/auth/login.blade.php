@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight text-slate-900">Sign in</h1>
    <p class="mt-1 text-sm text-slate-600">Use your assigned Shipyard CMMS account.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email address</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                aria-describedby="@error('email') email-error @enderror"
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900"
            >
            @error('email')
                <p id="email-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                required
                autocomplete="current-password"
                aria-describedby="@error('password') password-error @enderror"
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900"
            >
            @error('password')
                <p id="password-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input
                type="checkbox"
                name="remember"
                value="1"
                class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
            >
            Remember me
        </label>

        <button
            type="submit"
            class="w-full rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2"
        >
            Sign in
        </button>
    </form>
@endsection
