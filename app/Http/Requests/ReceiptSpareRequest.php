<?php

namespace App\Http\Requests;

use App\Models\SpareItem;
use Illuminate\Foundation\Http\FormRequest;

class ReceiptSpareRequest extends FormRequest
{
    public function authorize(): bool
    {
        $spareItem = $this->route('spareItem');

        return $spareItem instanceof SpareItem
            && $this->user()?->isChiefEngineer() ?? false;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
