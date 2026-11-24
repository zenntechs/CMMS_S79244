<?php

namespace App\Http\Requests;

use App\Enums\PlanTrigger;
use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenancePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isChiefEngineer() ?? false;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $trigger = $this->input('trigger_type');
            $asset = Asset::find($this->input('asset_id'));
            if ($asset && in_array($trigger, ['hours', 'dual'], true) && ! $asset->has_hour_meter) {
                $v->errors()->add('asset_id', 'Running-hour plans require an asset with a running-hour meter.');
            }
        });
    }

    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'asset_id' => ['required', 'uuid', 'exists:assets,id'],
            'code' => ['required', 'string', 'max:20', Rule::unique('maintenance_plans', 'code')->ignore($plan?->id)],
            'task_description' => ['required', 'string'],
            'trigger_type' => ['required', Rule::enum(PlanTrigger::class)],
            'interval_days' => ['nullable', 'integer', 'gt:0', 'required_if:trigger_type,calendar,dual'],
            'interval_hours' => ['nullable', 'numeric', 'gt:0', 'required_if:trigger_type,hours,dual'],
            'last_completion_date' => ['nullable', 'date'],
            'last_completion_hours' => ['nullable', 'numeric', 'min:0'],
            'source_label' => ['required', 'string', 'max:120'],
        ];
    }
}
