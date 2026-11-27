<?php

namespace App\Http\Requests;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;

class SubmitWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workOrder = $this->route('workOrder');

        return $workOrder instanceof WorkOrder
            && $this->user() !== null
            && $this->user()->can('submit', $workOrder);
    }

    public function rules(): array
    {
        return [
            'actual_completion_date' => ['required', 'date_format:Y-m-d'],
            'actual_completion_hours' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'completion_notes' => ['required', 'string', 'min:10', 'max:5000'],
            'evidence_ref' => ['nullable', 'string', 'max:255'],
        ];
    }
}
