<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenancePlanRequest;
use App\Http\Requests\UpdateMaintenancePlanRequest;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MaintenancePlanController extends Controller
{
    public function index(): View
    {
        $plans = MaintenancePlan::with('asset')
            ->when(request('trigger_type'), fn ($q, $t) => $q->where('trigger_type', $t))
            ->when(request('is_active'), fn ($q, $a) => $q->where('is_active', $a === '1'))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('plans.index', compact('plans'));
    }

    public function create(): View
    {
        $this->authorize('create', MaintenancePlan::class);
        $assets = Asset::orderBy('code')->get();

        return view('plans.create', compact('assets'));
    }

    public function store(StoreMaintenancePlanRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = false;
        MaintenancePlan::create($data);

        return redirect()->route('plans.index')->with('status', 'Maintenance plan created (inactive).');
    }

    public function show(MaintenancePlan $plan): View
    {
        $plan->load('asset', 'workOrders');

        return view('plans.show', compact('plan'));
    }

    public function edit(MaintenancePlan $plan): View
    {
        $this->authorize('update', $plan);
        $assets = Asset::orderBy('code')->get();

        return view('plans.edit', compact('plan', 'assets'));
    }

    public function update(UpdateMaintenancePlanRequest $request, MaintenancePlan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        return redirect()->route('plans.show', $plan)->with('status', 'Maintenance plan updated.');
    }
}
