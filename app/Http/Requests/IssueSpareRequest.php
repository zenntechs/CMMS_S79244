<?php

namespace App\Http\Requests;

use App\Models\SpareItem;
use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;

class IssueSpareRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workOrder = $this->route('workOrder');

        return $workOrder instanceof WorkOrder
            && $this->user() !== null
            && $this->user()->can('issueSpare', $workOrder);
    }

    public function rules(): array
    {
        return [
            'spare_item_id' => ['required', 'uuid', 'exists:spare_items,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $item = SpareItem::find($this->input('spare_item_id'));

            if ($item && ! $item->is_active) {
                $v->errors()->add('spare_item_id', 'Inactive spare items cannot be issued.');
            }
        });
    }
}
