<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\SpareItem;
use App\Models\SpareMovement;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\DueStatusCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index');
    }

    public function maintenance(Request $request): View|RedirectResponse
    {
        $filters = $request->validate([
            'asset_id' => ['nullable', 'uuid', 'exists:assets,id'],
            'subsystem' => ['nullable', 'string', 'max:80'],
            'criticality' => ['nullable', 'string', 'in:critical,high,medium,low'],
            'trigger_type' => ['nullable', 'string', 'in:calendar,hours,dual'],
            'status' => ['nullable', 'string', 'in:not_due,due,overdue,incomplete'],
            'due_from' => ['nullable', 'date_format:Y-m-d'],
            'due_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (! empty($filters['due_from']) && ! empty($filters['due_to']) && $filters['due_from'] > $filters['due_to']) {
            return redirect()->back()->withErrors(['due_to' => 'The due-to date must be on or after the due-from date.'])->withInput();
        }

        $rows = $this->maintenanceRows($filters);
        $subsystems = Asset::query()->distinct()->orderBy('subsystem')->pluck('subsystem');

        return view('reports.maintenance', [
            'rows' => $this->paginateCollection($rows, 20),
            'filters' => $filters,
            'subsystems' => $subsystems,
            'today' => CarbonImmutable::today(config('app.timezone')),
        ]);
    }

    public function exportMaintenance(Request $request): StreamedResponse|RedirectResponse
    {
        $filters = $request->validate([
            'asset_id' => ['nullable', 'uuid', 'exists:assets,id'],
            'subsystem' => ['nullable', 'string', 'max:80'],
            'criticality' => ['nullable', 'string', 'in:critical,high,medium,low'],
            'trigger_type' => ['nullable', 'string', 'in:calendar,hours,dual'],
            'status' => ['nullable', 'string', 'in:not_due,due,overdue,incomplete'],
            'due_from' => ['nullable', 'date_format:Y-m-d'],
            'due_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (! empty($filters['due_from']) && ! empty($filters['due_to']) && $filters['due_from'] > $filters['due_to']) {
            return redirect()->back()->withErrors(['due_to' => 'The due-to date must be on or after the due-from date.'])->withInput();
        }

        $rows = $this->maintenanceRows($filters);
        $today = CarbonImmutable::today(config('app.timezone'));

        return $this->streamCsv(
            'maintenance-due-report-'.now()->format('Ymd-His').'.csv',
            ['Plan', 'Asset', 'Subsystem', 'Criticality', 'Trigger', 'Status', 'Trigger cause', 'Due date', 'Hours due at', 'Last completion date', 'Last completion hours', 'Interval days', 'Interval hours', 'Latest reading hours', 'Latest reading at'],
            function ($handle) use ($rows): void {
                foreach ($rows as $row) {
                    $plan = $row['plan'];
                    $asset = $row['asset'];
                    $status = $row['status'];
                    $reading = $asset->latestMeterReading;

                    fputcsv($handle, [
                        $this->csvText($plan->code),
                        $this->csvText($asset->code),
                        $this->csvText($asset->subsystem),
                        $this->csvText($asset->criticality->label()),
                        $this->csvText($plan->trigger_type->label()),
                        $this->csvText($status->state->label()),
                        $this->csvText($status->trigger->label()),
                        $status->dueDate?->toDateString() ?? '',
                        $status->hoursDueAt ?? '',
                        $plan->last_completion_date?->toDateString() ?? '',
                        $plan->last_completion_hours !== null ? (string) $plan->last_completion_hours : '',
                        $plan->interval_days ?? '',
                        $plan->interval_hours !== null ? (string) $plan->interval_hours : '',
                        $reading !== null ? (string) $reading->reading_hours : '',
                        $reading?->reading_at?->format('Y-m-d H:i') ?? '',
                    ]);
                }
            },
        );
    }

    public function workOrders(Request $request): View|RedirectResponse
    {
        $filters = $request->validate([
            'state' => ['nullable', 'string', 'in:open,in_progress,submitted,closed'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'asset_id' => ['nullable', 'uuid', 'exists:assets,id'],
            'plan_id' => ['nullable', 'uuid', 'exists:maintenance_plans,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (! empty($filters['date_from']) && ! empty($filters['date_to']) && $filters['date_from'] > $filters['date_to']) {
            return redirect()->back()->withErrors(['date_to' => 'The end date must be on or after the start date.'])->withInput();
        }

        $query = $this->workOrderQuery($filters, $request->user());
        $technicians = User::where('role', UserRole::Technician)->where('is_active', true)->orderBy('name')->get();
        $assets = Asset::orderBy('code')->get();
        $plans = MaintenancePlan::orderBy('code')->get();

        return view('reports.work-orders', [
            'workOrders' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'technicians' => $technicians,
            'assets' => $assets,
            'plans' => $plans,
        ]);
    }

    public function exportWorkOrders(Request $request): StreamedResponse|RedirectResponse
    {
        $filters = $request->validate([
            'state' => ['nullable', 'string', 'in:open,in_progress,submitted,closed'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'asset_id' => ['nullable', 'uuid', 'exists:assets,id'],
            'plan_id' => ['nullable', 'uuid', 'exists:maintenance_plans,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (! empty($filters['date_from']) && ! empty($filters['date_to']) && $filters['date_from'] > $filters['date_to']) {
            return redirect()->back()->withErrors(['date_to' => 'The end date must be on or after the start date.'])->withInput();
        }

        $query = $this->workOrderQuery($filters, $request->user());

        return $this->streamCsv(
            'work-order-report-'.now()->format('Ymd-His').'.csv',
            ['Reference', 'Plan', 'Asset', 'State', 'Assigned to', 'Created by', 'Created at', 'Started at', 'Submitted at', 'Closed at', 'Actual completion date', 'Actual completion hours', 'Completion notes', 'Evidence reference', 'Approved by'],
            function ($handle) use ($query): void {
                foreach ($query->cursor() as $workOrder) {
                    fputcsv($handle, [
                        $this->csvText($workOrder->reference),
                        $this->csvText($workOrder->plan->code),
                        $this->csvText($workOrder->plan->asset->code),
                        $this->csvText($workOrder->state->label()),
                        $this->csvText($workOrder->assignee?->name ?? ''),
                        $this->csvText($workOrder->createdBy->name),
                        $workOrder->created_at?->format('Y-m-d H:i') ?? '',
                        $workOrder->started_at?->format('Y-m-d H:i') ?? '',
                        $workOrder->submitted_at?->format('Y-m-d H:i') ?? '',
                        $workOrder->closed_at?->format('Y-m-d H:i') ?? '',
                        $workOrder->actual_completion_date?->toDateString() ?? '',
                        $workOrder->actual_completion_hours !== null ? (string) $workOrder->actual_completion_hours : '',
                        $this->csvText($workOrder->completion_notes ?? ''),
                        $this->csvText($workOrder->evidence_ref ?? ''),
                        $this->csvText($workOrder->approvedBy?->name ?? ''),
                    ]);
                }
            },
        );
    }

    public function spares(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:low,ok'],
            'active' => ['nullable', 'string', 'in:active,inactive'],
            'search' => ['nullable', 'string', 'max:80'],
        ]);

        return view('reports.spares', [
            'items' => $this->spareItemQuery($filters)->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function exportSpares(Request $request): StreamedResponse|RedirectResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:low,ok'],
            'active' => ['nullable', 'string', 'in:active,inactive'],
            'search' => ['nullable', 'string', 'max:80'],
        ]);

        $query = $this->spareItemQuery($filters);

        return $this->streamCsv(
            'spare-inventory-report-'.now()->format('Ymd-His').'.csv',
            ['Code', 'Description', 'Unit', 'Quantity on hand', 'Reorder level', 'Status', 'Active'],
            function ($handle) use ($query): void {
                foreach ($query->cursor() as $item) {
                    fputcsv($handle, [
                        $this->csvText($item->code),
                        $this->csvText($item->description),
                        $this->csvText($item->unit),
                        (string) $item->quantity_on_hand,
                        (string) $item->reorder_level,
                        $this->csvText($item->is_active ? ($item->quantity_on_hand < $item->reorder_level ? 'Below reorder' : 'OK') : 'Inactive'),
                        $item->is_active ? 'Yes' : 'No',
                    ]);
                }
            },
        );
    }

    public function movements(Request $request): View|RedirectResponse
    {
        $filters = $request->validate([
            'spare_item_id' => ['nullable', 'uuid', 'exists:spare_items,id'],
            'movement_type' => ['nullable', 'string', 'in:issue,return,receipt,adjustment'],
            'work_order_id' => ['nullable', 'uuid', 'exists:work_orders,id'],
            'actor_id' => ['nullable', 'uuid', 'exists:users,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (! empty($filters['date_from']) && ! empty($filters['date_to']) && $filters['date_from'] > $filters['date_to']) {
            return redirect()->back()->withErrors(['date_to' => 'The end date must be on or after the start date.'])->withInput();
        }

        $query = $this->movementQuery($filters, $request->user());
        $spareItems = SpareItem::orderBy('code')->get();
        $technicians = User::where('role', UserRole::Technician)->orderBy('name')->get();
        $workOrders = $request->user()->isTechnician()
            ? WorkOrder::where('assigned_to', $request->user()->id)->orderBy('reference')->get()
            : WorkOrder::orderBy('reference')->get();

        return view('reports.movements', [
            'movements' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'spareItems' => $spareItems,
            'technicians' => $technicians,
            'workOrders' => $workOrders,
        ]);
    }

    public function exportMovements(Request $request): StreamedResponse|RedirectResponse
    {
        $filters = $request->validate([
            'spare_item_id' => ['nullable', 'uuid', 'exists:spare_items,id'],
            'movement_type' => ['nullable', 'string', 'in:issue,return,receipt,adjustment'],
            'work_order_id' => ['nullable', 'uuid', 'exists:work_orders,id'],
            'actor_id' => ['nullable', 'uuid', 'exists:users,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (! empty($filters['date_from']) && ! empty($filters['date_to']) && $filters['date_from'] > $filters['date_to']) {
            return redirect()->back()->withErrors(['date_to' => 'The end date must be on or after the start date.'])->withInput();
        }

        $query = $this->movementQuery($filters, $request->user());

        return $this->streamCsv(
            'spare-movement-report-'.now()->format('Ymd-His').'.csv',
            ['Occurred at', 'Spare item', 'Movement type', 'Quantity', 'Balance after', 'Work order', 'Actor', 'Reason'],
            function ($handle) use ($query): void {
                foreach ($query->cursor() as $movement) {
                    fputcsv($handle, [
                        $movement->occurred_at?->format('Y-m-d H:i') ?? '',
                        $this->csvText($movement->spareItem->code),
                        $this->csvText($movement->movement_type->value),
                        (string) $movement->quantity_delta,
                        (string) $movement->balance_after,
                        $this->csvText($movement->workOrder?->reference ?? ''),
                        $this->csvText($movement->actor->name),
                        $this->csvText($movement->reason ?? ''),
                    ]);
                }
            },
        );
    }

    private function maintenanceRows(array|Collection $filters): Collection
    {
        $filters = $filters instanceof Collection ? $filters->toArray() : $filters;

        $today = CarbonImmutable::today(config('app.timezone'));
        $calculator = new DueStatusCalculator;

        $plans = MaintenancePlan::query()
            ->where('is_active', true)
            ->with(['asset.latestMeterReading'])
            ->orderBy('code')
            ->get();

        $rows = $plans->map(function (MaintenancePlan $plan) use ($calculator, $today): array {
            $reading = $plan->asset->latestMeterReading;
            $currentHours = $reading !== null ? (string) $reading->reading_hours : null;
            $status = $calculator->calculateForPlan($plan, $currentHours, $today);

            return [
                'plan' => $plan,
                'asset' => $plan->asset,
                'status' => $status,
            ];
        });

        return $rows->filter(function (array $row) use ($filters): bool {
            if (! empty($filters['asset_id']) && $row['asset']->id !== $filters['asset_id']) {
                return false;
            }

            if (! empty($filters['subsystem']) && $row['asset']->subsystem !== $filters['subsystem']) {
                return false;
            }

            if (! empty($filters['criticality']) && $row['asset']->criticality->value !== $filters['criticality']) {
                return false;
            }

            if (! empty($filters['trigger_type']) && $row['plan']->trigger_type->value !== $filters['trigger_type']) {
                return false;
            }

            if (! empty($filters['status']) && $row['status']->state->value !== $filters['status']) {
                return false;
            }

            if (! empty($filters['due_from']) && $row['status']->dueDate !== null && $row['status']->dueDate->toDateString() < $filters['due_from']) {
                return false;
            }

            if (! empty($filters['due_to']) && $row['status']->dueDate !== null && $row['status']->dueDate->toDateString() > $filters['due_to']) {
                return false;
            }

            return true;
        })->values();
    }

    private function workOrderQuery(array $filters, User $user): Builder
    {
        $query = WorkOrder::query()
            ->with(['plan.asset', 'assignee', 'createdBy', 'approvedBy'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($user->isTechnician()) {
            $query->where('assigned_to', $user->id);
        }

        if (! empty($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        if (! empty($filters['assigned_to']) && ! $user->isTechnician()) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (! empty($filters['asset_id'])) {
            $query->whereHas('plan', fn ($q) => $q->where('asset_id', $filters['asset_id']));
        }

        if (! empty($filters['plan_id'])) {
            $query->where('plan_id', $filters['plan_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    private function spareItemQuery(array $filters): Builder
    {
        $query = SpareItem::query()->orderBy('code');

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($inner) use ($term): void {
                $inner->where('code', 'ilike', "%{$term}%")
                    ->orWhere('description', 'ilike', "%{$term}%")
                    ->orWhere('unit', 'ilike', "%{$term}%");
            });
        }

        if (($filters['status'] ?? null) === 'low') {
            $query->whereColumn('quantity_on_hand', '<', 'reorder_level');
        } elseif (($filters['status'] ?? null) === 'ok') {
            $query->whereColumn('quantity_on_hand', '>=', 'reorder_level');
        }

        if (($filters['active'] ?? null) === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['active'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        }

        return $query;
    }

    private function movementQuery(array $filters, User $user): Builder
    {
        $query = SpareMovement::query()
            ->with(['spareItem', 'workOrder', 'actor'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($user->isTechnician()) {
            $query->whereHas('workOrder', fn ($q) => $q->where('assigned_to', $user->id));
        }

        if (! empty($filters['spare_item_id'])) {
            $query->where('spare_item_id', $filters['spare_item_id']);
        }

        if (! empty($filters['movement_type'])) {
            $query->where('movement_type', $filters['movement_type']);
        }

        if (! empty($filters['work_order_id'])) {
            if ($user->isTechnician()) {
                $allowed = WorkOrder::where('id', $filters['work_order_id'])
                    ->where('assigned_to', $user->id)
                    ->exists();

                if (! $allowed) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('work_order_id', $filters['work_order_id']);
                }
            } else {
                $query->where('work_order_id', $filters['work_order_id']);
            }
        }

        if (! empty($filters['actor_id']) && ! $user->isTechnician()) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('occurred_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('occurred_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * @param  Collection<int, mixed>  $items
     */
    private function paginateCollection(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function streamCsv(string $filename, array $headers, callable $callback): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $callback): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            $callback($handle);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }

    private function csvText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (preg_match('/^\s*[=+\-@\t\r\n]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
