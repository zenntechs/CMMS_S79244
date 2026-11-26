<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeterReadingRequest;
use App\Models\Asset;
use App\Services\MeterReadingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MeterReadingController extends Controller
{
    public function index(Asset $asset): View
    {
        $asset->load('vessel');

        $readings = $asset->meterReadings()
            ->with('recordedBy')
            ->orderByDesc('reading_at')
            ->orderByDesc('id')
            ->paginate(15);

        $latest = $asset->latestMeterReading;

        return view('readings.index', compact('asset', 'readings', 'latest'));
    }

    public function store(
        StoreMeterReadingRequest $request,
        Asset $asset,
        MeterReadingService $service,
    ): RedirectResponse {
        $data = $request->validated();

        $service->record(
            asset: $asset,
            hours: $data['reading_hours'],
            at: CarbonImmutable::parse($data['reading_at'], config('app.timezone')),
            actor: $request->user(),
            note: $data['note'] ?? null,
        );

        return redirect()
            ->route('assets.readings.index', $asset)
            ->with('status', 'Meter reading recorded.');
    }
}
