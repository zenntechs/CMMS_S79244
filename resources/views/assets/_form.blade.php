@php
    $vessels = $vessels ?? \App\Models\Vessel::orderBy('name')->get();
@endphp

<div>
    <label class="block text-sm font-medium text-slate-700" for="vessel_id">Vessel</label>
    <select id="vessel_id" name="vessel_id" required class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        @foreach ($vessels as $vessel)
            <option value="{{ $vessel->id }}" @selected(old('vessel_id', $asset?->vessel_id) === $vessel->id)>{{ $vessel->name }}</option>
        @endforeach
    </select>
    @error('vessel_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700" for="code">Code</label>
    <input id="code" name="code" type="text" value="{{ old('code', $asset?->code) }}" required maxlength="20"
           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    @error('code')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700" for="name">Name</label>
    <input id="name" name="name" type="text" value="{{ old('name', $asset?->name) }}" required maxlength="120"
           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700" for="subsystem">Subsystem</label>
    <input id="subsystem" name="subsystem" type="text" value="{{ old('subsystem', $asset?->subsystem) }}" required maxlength="80"
           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    @error('subsystem')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700" for="location">Location</label>
    <input id="location" name="location" type="text" value="{{ old('location', $asset?->location) }}" maxlength="120"
           class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    @error('location')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700" for="criticality">Criticality</label>
    <select id="criticality" name="criticality" required class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        @foreach (\App\Enums\Criticality::cases() as $c)
            <option value="{{ $c->value }}" @selected(old('criticality', $asset?->criticality?->value) === $c->value)>{{ $c->label() }}</option>
        @endforeach
    </select>
    @error('criticality')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700" for="service_status">Service status</label>
    <select id="service_status" name="service_status" required class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        @foreach (\App\Enums\ServiceStatus::cases() as $s)
            <option value="{{ $s->value }}" @selected(old('service_status', $asset?->service_status?->value) === $s->value)>{{ $s->label() }}</option>
        @endforeach
    </select>
    @error('service_status')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

<label class="flex items-center gap-2 text-sm text-slate-700">
    <input type="checkbox" name="has_hour_meter" value="1" @checked(old('has_hour_meter', $asset?->has_hour_meter))
           class="h-4 w-4 rounded border-slate-300 text-slate-900">
    Has running-hour meter
</label>
