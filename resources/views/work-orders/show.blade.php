@extends('layouts.app')

@section('title', $workOrder->reference)

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $workOrder->reference }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700">{{ $workOrder->state->label() }}</span>
        </div>

        @if ($errors->any())
            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-700">Plan &amp; asset</h2>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Plan</dt><dd><a href="{{ route('plans.show', $workOrder->plan) }}" class="hover:underline">{{ $workOrder->plan->code }}</a></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Task</dt><dd class="text-right">{{ $workOrder->plan->task_description }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Asset</dt><dd><a href="{{ route('assets.show', $workOrder->plan->asset) }}" class="hover:underline">{{ $workOrder->plan->asset->code }}</a></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Trigger</dt><dd>{{ $workOrder->plan->trigger_type->label() }}</dd></div>
                </dl>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-700">Assignment</h2>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Created by</dt><dd>{{ $workOrder->createdBy->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Assigned to</dt><dd>{{ $workOrder->assignee?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Created at</dt><dd>{{ $workOrder->created_at->format('Y-m-d H:i') }}</dd></div>
                    @if ($workOrder->started_at)
                        <div class="flex justify-between"><dt class="text-slate-500">Started at</dt><dd>{{ $workOrder->started_at->format('Y-m-d H:i') }}</dd></div>
                    @endif
                    @if ($workOrder->submitted_at)
                        <div class="flex justify-between"><dt class="text-slate-500">Submitted at</dt><dd>{{ $workOrder->submitted_at->format('Y-m-d H:i') }}</dd></div>
                    @endif
                    @if ($workOrder->closed_at)
                        <div class="flex justify-between"><dt class="text-slate-500">Closed at</dt><dd>{{ $workOrder->closed_at->format('Y-m-d H:i') }}</dd></div>
                    @endif
                    @if ($workOrder->approvedBy)
                        <div class="flex justify-between"><dt class="text-slate-500">Approved by</dt><dd>{{ $workOrder->approvedBy->name }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>

        @if ($workOrder->actual_completion_date || $workOrder->actual_completion_hours !== null || $workOrder->completion_notes)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-700">Completion information</h2>
                <dl class="space-y-1 text-sm">
                    @if ($workOrder->actual_completion_date)
                        <div class="flex justify-between"><dt class="text-slate-500">Actual completion date</dt><dd>{{ $workOrder->actual_completion_date->format('Y-m-d') }}</dd></div>
                    @endif
                    @if ($workOrder->actual_completion_hours !== null)
                        <div class="flex justify-between"><dt class="text-slate-500">Actual completion hours</dt><dd>{{ number_format((float) $workOrder->actual_completion_hours, 2) }}</dd></div>
                    @endif
                    @if ($workOrder->completion_notes)
                        <div class="flex justify-between gap-6"><dt class="text-slate-500">Work performed</dt><dd class="text-right">{{ $workOrder->completion_notes }}</dd></div>
                    @endif
                    @if ($workOrder->evidence_ref)
                        <div class="flex justify-between"><dt class="text-slate-500">Evidence reference</dt><dd>{{ $workOrder->evidence_ref }}</dd></div>
                    @endif
                </dl>
            </div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Next action</h2>

            @if ($workOrder->state->value === 'open')
                @can('start', $workOrder)
                    <p class="mb-3 text-sm text-slate-600">Start the job when work begins. Opening the work order does not record any maintenance completion.</p>
                    <form method="POST" action="{{ route('work-orders.start', $workOrder) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Start work</button>
                    </form>
                @else
                    <p class="text-sm text-slate-600">This work order is open and has not been started. Only the assigned technician or the Chief Engineer can start it.</p>
                @endcan
            @elseif ($workOrder->state->value === 'in_progress')
                @can('submit', $workOrder)
                    <p class="mb-4 text-sm text-slate-600">Record the actual completion details. Submitting does not close the work order or advance the maintenance baseline; the Chief Engineer reviews it first.</p>
                    <form method="POST" action="{{ route('work-orders.submit', $workOrder) }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label for="actual_completion_date" class="block text-sm font-medium text-slate-700">Actual completion date <span class="text-rose-600">*</span></label>
                            <input type="date" name="actual_completion_date" id="actual_completion_date" value="{{ old('actual_completion_date', $workOrder->actual_completion_date?->format('Y-m-d')) }}" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        @if ($workOrder->plan->usesHours())
                            <div>
                                <label for="actual_completion_hours" class="block text-sm font-medium text-slate-700">Actual completion running hours <span class="text-rose-600">*</span></label>
                                <input type="number" step="0.01" min="0" name="actual_completion_hours" id="actual_completion_hours" value="{{ old('actual_completion_hours', $workOrder->actual_completion_hours !== null ? number_format((float) $workOrder->actual_completion_hours, 2, '.', '') : '') }}" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                        @endif
                        <div class="sm:col-span-2">
                            <label for="completion_notes" class="block text-sm font-medium text-slate-700">Work performed / completion notes <span class="text-rose-600">*</span></label>
                            <textarea name="completion_notes" id="completion_notes" rows="3" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('completion_notes', $workOrder->completion_notes) }}</textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="evidence_ref" class="block text-sm font-medium text-slate-700">Evidence reference (optional)</label>
                            <input type="text" name="evidence_ref" id="evidence_ref" value="{{ old('evidence_ref', $workOrder->evidence_ref) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Submit for approval</button>
                        </div>
                    </form>
                @else
                    <p class="text-sm text-slate-600">This work order is in progress. Only the assigned technician or the Chief Engineer can submit it for approval.</p>
                @endcan
            @elseif ($workOrder->state->value === 'submitted')
                @can('returnToTechnician', $workOrder)
                    <p class="mb-4 text-sm text-slate-600">Review the completion information, then approve the work in Phase 6C or return it to the technician with a reason. Returning preserves this submission in the event history.</p>
                    <form method="POST" action="{{ route('work-orders.return', $workOrder) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="reason" class="block text-sm font-medium text-slate-700">Return reason <span class="text-rose-600">*</span></label>
                            <textarea name="reason" id="reason" rows="3" required minlength="5" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                        </div>
                        <button type="submit" class="rounded-md border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-800 hover:bg-rose-100">Return to technician</button>
                    </form>

                    @can('close', $workOrder)
                        <div class="mt-4 border-t border-slate-200 pt-4">
                            <p class="mb-3 text-sm text-slate-600">Approving closes the work order and advances the maintenance plan baseline using the actual completion date and hours recorded above. This cannot be undone.</p>
                            <form method="POST" action="{{ route('work-orders.close', $workOrder) }}" onsubmit="return confirm('Approve and close this work order and advance the maintenance baseline?');">
                                @csrf
                                <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Approve and close</button>
                            </form>
                        </div>
                    @endcan
                @else
                    <p class="text-sm text-slate-600">This work order has been submitted and is awaiting Chief Engineer review. No action is required from you.</p>
                @endcan
            @else
                <p class="text-sm text-slate-600">This work order is closed and cannot be modified.</p>
            @endif
        </div>

        @if ($errors->any())
            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Spare parts</h2>
            @if ($workOrder->spareMovements->isEmpty())
                <p class="text-sm text-slate-500">No spare parts issued to this work order.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Occurred</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Type</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">Quantity</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">Balance after</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Actor</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($workOrder->spareMovements->sortByDesc('occurred_at') as $movement)
                                <tr>
                                    <td class="px-4 py-3 text-slate-600">{{ $movement->occurred_at->format('Y-m-d H:i') }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $movement->spareItem->code }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $movement->movement_type->value }}</td>
                                    <td class="px-4 py-3 text-right text-slate-900">{{ number_format((float) $movement->quantity_delta, 2) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-900">{{ number_format((float) $movement->balance_after, 2) }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $movement->actor->name }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $movement->reason ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @can('issueSpare', $workOrder)
                <form method="POST" action="{{ route('work-orders.spares.store', $workOrder) }}" class="mt-4 grid gap-3 border-t border-slate-200 pt-4 sm:grid-cols-4" onsubmit="const button=this.querySelector('button[type=submit]'); if(button){button.disabled=true;button.textContent='Processing...';}">
                    @csrf
                    <div class="sm:col-span-2">
                        <label for="spare_item_id" class="block text-sm font-medium text-slate-700">Spare item</label>
                        <select name="spare_item_id" id="spare_item_id" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select item</option>
                            @foreach ($spareItems as $spareItem)
                                <option value="{{ $spareItem->id }}" @selected(old('spare_item_id') === $spareItem->id)>
                                    {{ $spareItem->code }} — {{ $spareItem->description }} ({{ number_format((float) $spareItem->quantity_on_hand, 2) }} {{ $spareItem->unit }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="issue_quantity" class="block text-sm font-medium text-slate-700">Quantity</label>
                        <input type="number" step="0.01" min="0.01" name="quantity" id="issue_quantity" value="{{ old('quantity') }}" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="issue_reason" class="block text-sm font-medium text-slate-700">Reason (optional)</label>
                        <input type="text" name="reason" id="issue_reason" value="{{ old('reason') }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-4">
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Issue spare part</button>
                    </div>
                </form>
            @elseif ($workOrder->state->isActive())
                <p class="mt-3 text-sm text-slate-600">Only the assigned technician or the Chief Engineer can issue spare parts to this work order.</p>
            @else
                <p class="mt-3 text-sm text-slate-600">Spare parts cannot be issued to a closed work order.</p>
            @endif
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Event history</h2>
            @if ($workOrder->events->isEmpty())
                <p class="text-sm text-slate-500">No events recorded.</p>
            @else
                <ol class="space-y-2 text-sm">
                    @foreach ($workOrder->events->sortBy('occurred_at') as $event)
                        <li class="flex flex-wrap gap-2">
                            <span class="font-medium text-slate-900">{{ $event->event_type->value }}</span>
                            <span class="text-slate-500">{{ $event->occurred_at->format('Y-m-d H:i') }}</span>
                            <span class="text-slate-600">by {{ $event->actor->name }}</span>
                            @if ($event->from_state || $event->to_state)
                                <span class="text-slate-500">({{ $event->from_state?->value ?? '—' }} → {{ $event->to_state?->value ?? '—' }})</span>
                            @endif
                            @if ($event->reason)
                                <span class="text-slate-600">— {{ $event->reason }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>
@endsection
