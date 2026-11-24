<?php

namespace App\Http\Controllers;

use App\Models\MaintenancePlan;
use Illuminate\Http\RedirectResponse;

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

        $plan->update(['is_active' => true]);

        return back()->with('status', 'Plan activated.');
    }

    public function destroy(MaintenancePlan $plan): RedirectResponse
    {
        $this->authorize('activate', $plan);
        $plan->update(['is_active' => false]);

        return back()->with('status', 'Plan deactivated.');
    }
}
