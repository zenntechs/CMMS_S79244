@php
    $assets = $assets ?? \App\Models\Asset::orderBy('code')->get();
@endphp

<div>
    <label class="block text-sm font-medium text-slate-700" for="asset_id">Asset</label>
    <select id="asset_id" name="asset_id" required class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        @foreach ($assets as $a)
            <option value="{{ $a->id }}" @selected(old('asset_id', $plan?->asset_id) === $a->id)>{{ $a->code }} — {{ $a->name }}{{ $a->has_hour_meter ? '' : ' (no hour meter)' }}</option>
        @endforeach
    </select>
    @error('asset_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700" for="code">Code</label>
    <input id="code" name="code" type="text" value="{{ old('code', $plan?->code) }}" required maxlength="20"
           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    @error('code')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700" for="task_description">Task description</label>
    <textarea id="task_description" name="task_description" required rows="3"
              class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('task_description', $plan?->task_description) }}</textarea>
    @error('task_description')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700" for="trigger_type">Trigger type</label>
    <select id="trigger_type" name="trigger_type" required class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        @foreach (\App\Enums\PlanTrigger::cases() as $t)
            <option value="{{ $t->value }}" @selected(old('trigger_type', $plan?->trigger_type?->value) === $t->value)>{{ $t->label() }}</option>
        @endforeach
    </select>
    @error('trigger_type')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700" for="interval_days">Interval (days)</label>
    <input id="interval_days" name="interval_days" type="number" min="1" value="{{ old('interval_days', $plan?->interval_days) }}"
           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    @error('interval_days')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700" for="interval_hours">Interval (hours)</label>
    <input id="interval_hours" name="interval_hours" type="number" step="0.01" min="0.01" value="{{ old('interval_hours', $plan?->interval_hours) }}"
           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    @error('interval_hours')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700" for="last_completion_date">Last completion date</label>
    <input id="last_completion_date" name="last_completion_date" type="date" value="{{ old('last_completion_date', $plan?->last_completion_date?->toDateString()) }}"
           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    @error('last_completion_date')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700" for="last_completion_hours">Last completion hours</label>
    <input id="last_completion_hours" name="last_completion_hours" type="number" step="0.01" min="0" value="{{ old('last_completion_hours', $plan?->last_completion_hours) }}"
           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    @error('last_completion_hours')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700" for="source_label">Source label</label>
    <input id="source_label" name="source_label" type="text" value="{{ old('source_label', $plan?->source_label ?? 'Synthetic teaching scenario') }}" required maxlength="120"
           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    @error('source_label')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
