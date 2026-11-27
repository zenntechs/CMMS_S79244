<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpareItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isChiefEngineer() ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'unique:spare_items,code'],
            'description' => ['required', 'string', 'max:160'],
            'unit' => ['required', 'string', 'max:20'],
            'reorder_level' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
