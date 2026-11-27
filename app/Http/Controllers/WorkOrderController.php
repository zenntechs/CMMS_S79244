<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\ReturnWorkOrderRequest;
use App\Http\Requests\StoreWorkOrderRequest;
use App\Http\Requests\SubmitWorkOrderRequest;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrderLifecycleService;
use App\Services\WorkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkOrderController extends Controller
{
    public function index(): View
    {
        $query = WorkOrder::with(['plan.asset', 'assignee', 'createdBy'])
            ->when(request('state'), fn ($q, $s) => $q->where('state', $s))
            ->when(request('assigned_to'), fn ($q, $u) => $q->where('assigned_to', $u))
            ->when(request('plan_id'), fn ($q, $p) => $q->where('plan_id', $p))
            ->when(request('asset_id'), fn ($q, $a) => $q->whereHas('plan', fn ($p) => $p->where('asset_id', $a)))
            ->when(request('search'), function ($q, $term): void {
                $q->where(function ($inner) use ($term): void {
                    $inner->where('reference', 'like', '%'.$term.'%')
                        ->orWhereHas('plan', fn ($p) => $p->where('task_description', 'like', '%'.$term.'%')
                            ->orWhere('code', 'like', '%'.$term.'%'));
                });
            })
            ->orderByDesc('created_at');

        $user = auth()->user();
        if ($user->isTechnician()) {
            $query->where('assigned_to', $user->id);
        }

        $workOrders = $query->paginate(20)->withQueryString();

        $technicians = User::where('role', UserRole::Technician)->where('is_active', true)->orderBy('name')->get();

        return view('work-orders.index', compact('workOrders', 'technicians'));
    }

    public function show(WorkOrder $workOrder): View
    {
        $this->authorize('view', $workOrder);

        $workOrder->load(['plan.asset.latestMeterReading', 'assignee', 'createdBy', 'approvedBy', 'events.actor']);

        return view('work-orders.show', compact('workOrder'));
    }

    public function create(): View
    {
        $this->authorize('create', WorkOrder::class);

        $plans = MaintenancePlan::with('asset')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $technicians = User::where('role', UserRole::Technician)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('work-orders.create', compact('plans', 'technicians'));
    }

    public function store(StoreWorkOrderRequest $request, WorkOrderService $service): RedirectResponse
    {
        $plan = MaintenancePlan::findOrFail($request->validated()['plan_id']);
        $assignee = User::findOrFail($request->validated()['assigned_to']);

        try {
            $workOrder = $service->create($plan, $request->user(), $assignee);
        } catch (ValidationException $exception) {
            return redirect()->back()->withInput()->withErrors($exception->errors());
        }

        return redirect()
            ->route('work-orders.show', $workOrder)
            ->with('status', 'Work order created and assigned.');
    }

    public function start(WorkOrder $workOrder, WorkOrderLifecycleService $service): RedirectResponse
    {
        $this->authorize('start', $workOrder);

        try {
            $service->start($workOrder->id, request()->user());
        } catch (ValidationException $exception) {
            return redirect()->back()->withErrors($exception->errors());
        }

        return redirect()
            ->route('work-orders.show', $workOrder)
            ->with('status', 'Work started. Record the completion details when the job is finished.');
    }

    public function submit(
        SubmitWorkOrderRequest $request,
        WorkOrder $workOrder,
        WorkOrderLifecycleService $service,
    ): RedirectResponse {
        $this->authorize('submit', $workOrder);

        try {
            $service->submit($workOrder->id, $request->user(), $request->validated());
        } catch (ValidationException $exception) {
            return redirect()->back()->withInput()->withErrors($exception->errors());
        }

        return redirect()
            ->route('work-orders.show', $workOrder)
            ->with('status', 'Work submitted for Chief Engineer approval.');
    }

    public function returnToTechnician(
        ReturnWorkOrderRequest $request,
        WorkOrder $workOrder,
        WorkOrderLifecycleService $service,
    ): RedirectResponse {
        $this->authorize('returnToTechnician', $workOrder);

        try {
            $service->returnToTechnician($workOrder->id, $request->user(), $request->validated()['reason']);
        } catch (ValidationException $exception) {
            return redirect()->back()->withInput()->withErrors($exception->errors());
        }

        return redirect()
            ->route('work-orders.show', $workOrder)
            ->with('status', 'Work returned to the assigned technician.');
    }
}
