@extends('layouts.app')

@section('title', 'New work order')

@section('content')
    <div class="max-w-2xl space-y-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">New work order</h1>
        <p class="text-sm text-slate-500">Create a work order against an active maintenance plan and assign it to an active technician. Only one active work order may exist per plan.</p>

        <form method="POST" action="{{ route('work-orders.store') }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700" for="plan_id">Maintenance plan</label>
                <select id="plan_id" name="plan_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    <option value="">Select an active plan</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" data-asset="{{ $plan->asset_id }}" @selected(old('plan_id') === $plan->id)>
                            {{ $plan->code }} — {{ $plan->asset->code }} ({{ $plan->task_description }})
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="asset_id" id="asset_id" value="{{ old('asset_id') }}">
                @error('plan_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @error('asset_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700" for="assigned_to">Assign to technician</label>
                <select id="assigned_to" name="assigned_to" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    <option value="">Select a technician</option>
                    @foreach ($technicians as $tech)
                        <option value="{{ $tech->id }}" @selected(old('assigned_to') === $tech->id)>{{ $tech->name }} ({{ $tech->email }})</option>
                    @endforeach
                </select>
                @error('assigned_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Create work order</button>
                <a href="{{ route('work-orders.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const plan = document.getElementById('plan_id');
            const asset = document.getElementById('asset_id');
            function sync() {
                const opt = plan.options[plan.selectedIndex];
                if (opt && opt.dataset.asset) asset.value = opt.dataset.asset;
            }
            plan.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection