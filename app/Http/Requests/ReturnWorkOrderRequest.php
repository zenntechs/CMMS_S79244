<?php

namespace App\Http\Requests;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;

class ReturnWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workOrder = $this->route('workOrder');

        return $workOrder instanceof WorkOrder
            && $this->user() !== null
            && $this->user()->can('returnToTechnician', $workOrder);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
