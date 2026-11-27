<?php

namespace App\Http\Controllers;

use App\Enums\DueState;
use App\Enums\ServiceStatus;
use App\Enums\WorkOrderState;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\SpareItem;
use App\Models\Vessel;
use App\Models\WorkOrder;
use App\Services\DueStatusCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $today = CarbonImmutable::today(config('app.timezone'));

        $vessel = Vessel::withCount('assets')->first();

        $assets = Asset::query()->get();
        $activeAssets = $assets->where('service_status', ServiceStatus::InService)->count();
        $withdrawnAssets = $assets->where('service_status', ServiceStatus::Withdrawn)->count();

        $plans = MaintenancePlan::query()
            ->where('is_active', true)
            ->with('asset.latestMeterReading')
            ->orderBy('code')
            ->get();

        $calculator = new DueStatusCalculator;
        $planStatuses = $plans->map(function (MaintenancePlan $plan) use ($calculator, $today): array {
            $reading = $plan->asset->latestMeterReading;
            $currentHours = $reading !== null ? (string) $reading->reading_hours : null;

            return [
                'plan' => $plan,
                'status' => $calculator->calculateForPlan($plan, $currentHours, $today),
            ];
        });

        $maintenanceCounts = [
            'not_due' => $planStatuses->filter(fn (array $row): bool => $row['status']->state === DueState::NotDue)->count(),
            'due' => $planStatuses->filter(fn (array $row): bool => $row['status']->state === DueState::Due)->count(),
            'overdue' => $planStatuses->filter(fn (array $row): bool => $row['status']->state === DueState::Overdue)->count(),
            'incomplete' => $planStatuses->filter(fn (array $row): bool => $row['status']->state === DueState::Incomplete)->count(),
        ];

        $attentionPlans = $planStatuses
            ->filter(fn (array $row): bool => in_array($row['status']->state, [DueState::Overdue, DueState::Due, DueState::Incomplete], true))
            ->sortBy(fn (array $row): int => match ($row['status']->state) {
                DueState::Overdue => 0,
                DueState::Due => 1,
                DueState::Incomplete => 2,
                default => 3,
            })
            ->take(8)
            ->values();

        $workOrderQuery = WorkOrder::query()->with(['plan.asset', 'assignee']);

        if ($user->isTechnician()) {
            $workOrderQuery->where('assigned_to', $user->id);
        }

        $workOrders = $workOrderQuery->get();

        $workOrderCounts = [
            'open' => $workOrders->where('state', WorkOrderState::Open)->count(),
            'in_progress' => $workOrders->where('state', WorkOrderState::InProgress)->count(),
            'submitted' => $workOrders->where('state', WorkOrderState::Submitted)->count(),
            'closed' => $workOrders->where('state', WorkOrderState::Closed)->count(),
        ];

        $workOrderCounts['active'] = $workOrderCounts['open'] + $workOrderCounts['in_progress'] + $workOrderCounts['submitted'];

        $spareItems = SpareItem::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $lowStockItems = $spareItems
            ->filter(fn (SpareItem $item): bool => $item->quantity_on_hand < $item->reorder_level)
            ->values();

        return view('dashboard.index', [
            'user' => $user,
            'vessel' => $vessel,
            'assetCount' => $assets->count(),
            'activeAssetCount' => $activeAssets,
            'withdrawnAssetCount' => $withdrawnAssets,
            'planCount' => $plans->count(),
            'maintenanceCounts' => $maintenanceCounts,
            'attentionPlans' => $attentionPlans,
            'workOrderCounts' => $workOrderCounts,
            'spareItemCount' => $spareItems->count(),
            'lowStockCount' => $lowStockItems->count(),
            'lowStockItems' => $lowStockItems->take(8),
            'totalStockQuantity' => number_format((float) $spareItems->sum('quantity_on_hand'), 2),
        ]);
    }
}
