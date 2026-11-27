<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\MaintenancePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PlanActivationController extends Controller
{
    public function store(MaintenancePlan $plan): RedirectResponse
    {
        $this->authorize('activate', $plan);

        // Activation requires complete baselines (also enforced by DB CHECK).
        $errors = [];
        if ($plan->trigger_type->usesCalendar() && $plan->last_completion_date === null) {
            $errors[] = 'calendar baseline';
        }
        if ($plan->trigger_type->usesHours() && $plan->last_completion_hours === null) {
            $errors[] = 'running-hour baseline';
        }
        if ($errors !== []) {
            return back()->withErrors(['activation' => 'Cannot activate: missing '.implode(' and ', $errors).'.']);
        }

        DB::transaction(function () use ($plan, $request): void {
            $plan->update(['is_active' => true]);

            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'action' => 'maintenance_plan.activated',
                'entity_type' => 'maintenance_plan',
                'entity_id' => $plan->id,
                'before' => ['is_active' => false],
                'after' => ['is_active' => true],
                'reason' => null,
                'occurred_at' => now(),
            ]);
        });

        return back()->with('status', 'Plan activated.');
    }

    public function destroy(MaintenancePlan $plan): RedirectResponse
    {
        $this->authorize('activate', $plan);
        DB::transaction(function () use ($plan, $request): void {
            $plan->update(['is_active' => false]);

            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'action' => 'maintenance_plan.deactivated',
                'entity_type' => 'maintenance_plan',
                'entity_id' => $plan->id,
                'before' => ['is_active' => true],
                'after' => ['is_active' => false],
                'reason' => null,
                'occurred_at' => now(),
            ]);
        });

        return back()->with('status', 'Plan deactivated.');
    }
}
