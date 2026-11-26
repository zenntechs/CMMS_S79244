<form method="POST" action="{{ route('assets.readings.store', $asset) }}" class="space-y-4">
    @csrf

    @if ($errors->any())
        <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="reading_hours" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Running hours</label>
            <input id="reading_hours" name="reading_hours" type="number" step="0.01" min="0" required
                   value="{{ old('reading_hours') }}"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="reading_at" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Reading timestamp</label>
            <input id="reading_at" name="reading_at" type="datetime-local" required
                   value="{{ old('reading_at', now()->format('Y-m-d\TH:i')) }}"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-slate-500">Asia/Kuala_Lumpur; future timestamps are rejected.</p>
        </div>
        <div>
            <label for="note" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Note (optional)</label>
            <input id="note" name="note" type="text" maxlength="1000" value="{{ old('note') }}"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
    </div>

    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
        Record reading
    </button>
</form>
