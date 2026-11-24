<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\Vessel;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(): View
    {
        $assets = Asset::with('vessel')
            ->when(request('search'), fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('code', 'ilike', "%{$s}%")
                    ->orWhere('name', 'ilike', "%{$s}%")
                    ->orWhere('subsystem', 'ilike', "%{$s}%");
            }))
            ->when(request('subsystem'), fn ($q, $s) => $q->where('subsystem', $s))
            ->when(request('service_status'), fn ($q, $s) => $q->where('service_status', $s))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        $subsystems = Asset::distinct()->orderBy('subsystem')->pluck('subsystem');

        return view('assets.index', compact('assets', 'subsystems'));
    }

    public function create(): View
    {
        $this->authorize('create', Asset::class);
        $vessels = Vessel::orderBy('name')->get();

        return view('assets.create', compact('vessels'));
    }

    public function store(StoreAssetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['has_hour_meter'] = $request->boolean('has_hour_meter');
        Asset::create($data);

        return redirect()->route('assets.index')->with('status', 'Asset created.');
    }

    public function show(Asset $asset): View
    {
        $asset->load('vessel', 'maintenancePlans', 'meterReadings');

        return view('assets.show', compact('asset'));
    }

    public function edit(Asset $asset): View
    {
        $this->authorize('update', $asset);
        $vessels = Vessel::orderBy('name')->get();

        return view('assets.edit', compact('asset', 'vessels'));
    }

    public function update(UpdateAssetRequest $request, Asset $asset): RedirectResponse
    {
        $data = $request->validated();
        $data['has_hour_meter'] = $request->boolean('has_hour_meter');
        $asset->update($data);

        return redirect()->route('assets.show', $asset)->with('status', 'Asset updated.');
    }
}
