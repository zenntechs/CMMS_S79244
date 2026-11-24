<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Sign in') — {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col font-sans text-slate-900 antialiased">
    <main class="flex flex-1 items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Shipyard CMMS</p>
                <p class="mt-1 text-xs text-slate-500">Student ID S79244</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                @yield('content')
            </div>
        </div>
    </main>
</body>
</html>
