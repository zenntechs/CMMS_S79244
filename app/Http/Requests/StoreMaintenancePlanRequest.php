<?php

namespace App\Http\Requests;

use App\Enums\PlanTrigger;
use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenancePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isChiefEngineer() ?? false;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'uuid', 'exists:assets,id'],
            'code' => ['required', 'string', 'max:20', 'unique:maintenance_plans,code'],
            'task_description' => ['required', 'string'],
            'trigger_type' => ['required', Rule::enum(PlanTrigger::class)],
            'interval_days' => ['nullable', 'integer', 'gt:0', 'required_if:trigger_type,calendar,dual'],
            'interval_hours' => ['nullable', 'numeric', 'gt:0', 'required_if:trigger_type,hours,dual'],
            'last_completion_date' => ['nullable', 'date'],
            'last_completion_hours' => ['nullable', 'numeric', 'min:0'],
            'source_label' => ['required', 'string', 'max:120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $trigger = $this->input('trigger_type');
            $asset = Asset::find($this->input('asset_id'));
            if ($asset && in_array($trigger, ['hours', 'dual'], true) && ! $asset->has_hour_meter) {
                $v->errors()->add('asset_id', 'Running-hour plans require an asset with a running-hour meter.');
            }
            if (($trigger === 'hours' || $trigger === 'dual') && $this->boolean('is_active') && ! $this->filled('last_completion_hours')) {
                $v->errors()->add('last_completion_hours', 'Running-hour baseline is required to activate this plan.');
            }
            if (($trigger === 'calendar' || $trigger === 'dual') && $this->boolean('is_active') && ! $this->filled('last_completion_date')) {
                $v->errors()->add('last_completion_date', 'Calendar baseline is required to activate this plan.');
            }
        });
    }
}
