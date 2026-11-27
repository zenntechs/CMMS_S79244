<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenancePlanRequest;
use App\Http\Requests\UpdateMaintenancePlanRequest;
use App\Models\Asset;
use App\Models\AuditEvent;
use App\Models\MaintenancePlan;
use App\Services\DueStatusCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaintenancePlanController extends Controller
{
    public function index(): View
    {
        $plans = MaintenancePlan::with('asset.latestMeterReading')
            ->when(request('trigger_type'), fn ($q, $t) => $q->where('trigger_type', $t))
            ->when(request('is_active'), fn ($q, $a) => $q->where('is_active', $a === '1'))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        $calculator = new DueStatusCalculator;
        $today = CarbonImmutable::today(config('app.timezone'));
        $dueStatuses = [];
        foreach ($plans as $plan) {
            $reading = $plan->asset->latestMeterReading;
            $dueStatuses[$plan->id] = $calculator->calculateForPlan($plan, $reading ? (string) $reading->reading_hours : null, $today);
        }

        return view('plans.index', compact('plans', 'dueStatuses'));
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

        $plan = DB::transaction(function () use ($data, $request): MaintenancePlan {
            $plan = MaintenancePlan::create($data);

            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'action' => 'maintenance_plan.created',
                'entity_type' => 'maintenance_plan',
                'entity_id' => $plan->id,
                'before' => null,
                'after' => $data,
                'reason' => null,
                'occurred_at' => now(),
            ]);

            return $plan;
        });

        return redirect()->route('plans.index')->with('status', 'Maintenance plan created (inactive).');
    }

    public function show(MaintenancePlan $plan): View
    {
        $plan->load('asset.latestMeterReading', 'workOrders', 'baselineHistory.changedBy');
        $reading = $plan->asset->latestMeterReading;
        $calculator = new DueStatusCalculator;
        $today = CarbonImmutable::today(config('app.timezone'));
        $dueStatus = $calculator->calculateForPlan($plan, $reading ? (string) $reading->reading_hours : null, $today);

        return view('plans.show', compact('plan', 'dueStatus'));
    }

    public function edit(MaintenancePlan $plan): View
    {
        $this->authorize('update', $plan);
        $assets = Asset::orderBy('code')->get();

        return view('plans.edit', compact('plan', 'assets'));
    }

    public function update(UpdateMaintenancePlanRequest $request, MaintenancePlan $plan): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($plan, $data, $request): void {
            $before = $plan->getAttributes();
            $plan->update($data);

            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'action' => 'maintenance_plan.updated',
                'entity_type' => 'maintenance_plan',
                'entity_id' => $plan->id,
                'before' => $before,
                'after' => $plan->getChanges() ?: $data,
                'reason' => null,
                'occurred_at' => now(),
            ]);
        });

        return redirect()->route('plans.show', $plan)->with('status', 'Maintenance plan updated.');
    }
}
