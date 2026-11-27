<?php

namespace App\Http\Requests;

use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isChiefEngineer() ?? false;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'uuid', 'exists:assets,id'],
            'plan_id' => ['required', 'uuid', 'exists:maintenance_plans,id'],
            'assigned_to' => ['required', 'uuid', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $plan = MaintenancePlan::find($this->input('plan_id'));
            $asset = Asset::find($this->input('asset_id'));
            $technician = User::find($this->input('assigned_to'));

            if ($plan && $asset && $plan->asset_id !== $asset->id) {
                $v->errors()->add('plan_id', 'The selected plan does not belong to the selected asset.');
            }

            if ($plan && ! $plan->is_active) {
                $v->errors()->add('plan_id', 'Work orders can only be created for active maintenance plans.');
            }

            if ($technician && (! $technician->isTechnician() || ! $technician->is_active)) {
                $v->errors()->add('assigned_to', 'The assignee must be an active technician.');
            }
        });
    }
}
