@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Dashboard</h1>
            <p class="mt-1 text-sm text-slate-600">
                Signed in as {{ $user->name }} · {{ $user->role->label() }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Your role</h2>
                <p class="mt-2 text-lg font-semibold text-slate-900">{{ $user->role->label() }}</p>
                <p class="mt-1 text-sm text-slate-600">
                    @switch($user->role)
                        @case(\App\Enums\UserRole::ChiefEngineer)
                            Full access to plans, assignments, review, closure, spares and audit once those modules are delivered.
                            @break
                        @case(\App\Enums\UserRole::Technician)
                            Access is limited to assigned work orders and permitted meter/spare actions once those modules are delivered.
                            @break
                        @default
                            Read-only access to authorised screens. No mutating route is available to this role.
                    @endswitch
                </p>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Role enforcement</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Every authenticated route is protected by the <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">auth</code>
                    and <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">active</code> middleware. Coarse role gates use
                    <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">role</code> and record-level rules use policies.
                </p>
                @if ($user->isChiefEngineer())
                    <a
                        href="{{ route('admin.users.index') }}"
                        class="mt-4 inline-flex rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2"
                    >
                        View application users
                    </a>
                @endif
            </section>
        </div>

        <section class="rounded-xl border border-dashed border-slate-300 bg-white p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Phase 1 scope</h2>
            <p class="mt-2 text-sm text-slate-600">
                This phase delivers the container platform, PostgreSQL connection, authentication and the
                Chief Engineer / Technician / Viewer role boundary. Vessels, assets, maintenance plans,
                work orders and spare parts begin in Phase 2.
            </p>
        </section>
    </div>
@endsection
