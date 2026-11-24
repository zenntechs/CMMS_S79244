<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>403 — Forbidden — {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col font-sans text-slate-900 antialiased">
    <main class="flex flex-1 items-center justify-center px-4 py-12">
        <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-red-700">403 — Forbidden</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Permission denied</h1>
            <p class="mt-3 text-sm text-slate-600">
                Your account is signed in, but it does not hold the role required for this page.
                The request was rejected on the server; nothing was changed.
            </p>

            <a
                href="{{ auth()->check() ? route('dashboard') : route('login') }}"
                class="mt-6 inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2"
            >
                {{ auth()->check() ? 'Back to dashboard' : 'Go to sign in' }}
            </a>
        </div>
    </main>
</body>
</html>
