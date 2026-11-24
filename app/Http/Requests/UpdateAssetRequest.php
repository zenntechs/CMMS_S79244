<?php

namespace App\Http\Requests;

use App\Enums\Criticality;
use App\Enums\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isChiefEngineer() ?? false;
    }

    public function rules(): array
    {
        $asset = $this->route('asset');

        return [
            'vessel_id' => ['required', 'uuid', 'exists:vessels,id'],
            'code' => ['required', 'string', 'max:20', Rule::unique('assets', 'code')->ignore($asset?->id)],
            'name' => ['required', 'string', 'max:120'],
            'subsystem' => ['required', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:120'],
            'criticality' => ['required', Rule::enum(Criticality::class)],
            'service_status' => ['required', Rule::enum(ServiceStatus::class)],
            'has_hour_meter' => ['boolean'],
        ];
    }
}
