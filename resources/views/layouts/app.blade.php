<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Shipyard CMMS') — {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-900 antialiased">
    <div class="flex min-h-full flex-col">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <a href="{{ route('dashboard') }}" class="text-lg font-semibold tracking-tight text-slate-900">
                    Shipyard CMMS
                </a>

                <nav class="flex items-center gap-1 text-sm" aria-label="Primary">
                    <a href="{{ route('dashboard') }}" @class(['rounded-md px-3 py-2 font-medium', 'bg-slate-900 text-white' => request()->routeIs('dashboard'), 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! request()->routeIs('dashboard')])>Dashboard</a>
                    <a href="{{ route('vessel.show') }}" @class(['rounded-md px-3 py-2 font-medium', 'bg-slate-900 text-white' => request()->routeIs('vessel.*'), 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! request()->routeIs('vessel.*')])>Vessel</a>
                    <a href="{{ route('assets.index') }}" @class(['rounded-md px-3 py-2 font-medium', 'bg-slate-900 text-white' => request()->routeIs('assets.*'), 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! request()->routeIs('assets.*')])>Assets</a>
                    <a href="{{ route('plans.index') }}" @class(['rounded-md px-3 py-2 font-medium', 'bg-slate-900 text-white' => request()->routeIs('plans.*'), 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! request()->routeIs('plans.*')])>Plans</a>
                    <a href="{{ route('work-orders.index') }}" @class(['rounded-md px-3 py-2 font-medium', 'bg-slate-900 text-white' => request()->routeIs('work-orders.*'), 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! request()->routeIs('work-orders.*')])>Work orders</a>
                    <a href="{{ route('spares.index') }}" @class(['rounded-md px-3 py-2 font-medium', 'bg-slate-900 text-white' => request()->routeIs('spares.*'), 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! request()->routeIs('spares.*')])>Spares</a>
                    <a href="{{ route('reports.index') }}" @class(['rounded-md px-3 py-2 font-medium', 'bg-slate-900 text-white' => request()->routeIs('reports.*'), 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! request()->routeIs('reports.*')])>Reports</a>
                    @can('viewAny', App\Models\AuditEvent::class)
                        <a href="{{ route('audit.index') }}" @class(['rounded-md px-3 py-2 font-medium', 'bg-slate-900 text-white' => request()->routeIs('audit.*'), 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! request()->routeIs('audit.*')])>Audit</a>
                    @endcan

                    @if (auth()->user()?->isChiefEngineer())
                        <a
                            href="{{ route('admin.users.index') }}"
                            @class([
                                'rounded-md px-3 py-2 font-medium',
                                'bg-slate-900 text-white' => request()->routeIs('admin.users.*'),
                                'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! request()->routeIs('admin.users.*'),
                            ])
                        >
                            Users
                        </a>
                    @endif
                </nav>

                <div class="flex items-center gap-3 text-sm">
                    <div class="text-right">
                        <p class="font-medium text-slate-900">{{ auth()->user()?->name }}</p>
                        <p class="text-xs text-slate-500">{{ auth()->user()?->role->label() }}</p>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-md border border-slate-300 px-3 py-2 font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2"
                        >
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">
            @if (session('status'))
                <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-4 text-xs text-slate-500 sm:px-6">
                Shipyard CMMS · Student ID S79244 · Phase 1 — authentication and role-based access control
            </div>
        </footer>
    </div>
</body>
</html>
