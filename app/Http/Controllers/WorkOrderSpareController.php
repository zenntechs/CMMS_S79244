<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueSpareRequest;
use App\Models\SpareItem;
use App\Models\WorkOrder;
use App\Services\SpareMovementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class WorkOrderSpareController extends Controller
{
    public function store(IssueSpareRequest $request, WorkOrder $workOrder, SpareMovementService $service): RedirectResponse
    {
        $this->authorize('issueSpare', $workOrder);

        $spareItem = SpareItem::findOrFail($request->validated()['spare_item_id']);

        try {
            $service->issue($spareItem, $workOrder, $request->validated()['quantity'], $request->user(), $request->validated()['reason'] ?? null);
        } catch (ValidationException $exception) {
            return redirect()->back()->withInput()->withErrors($exception->errors());
        } catch (AuthorizationException $exception) {
            return redirect()->back()->withInput()->withErrors(['work_order' => $exception->getMessage()]);
        }

        return redirect()->route('work-orders.show', $workOrder)->with('status', 'Spare part issued to work order.');
    }
}
