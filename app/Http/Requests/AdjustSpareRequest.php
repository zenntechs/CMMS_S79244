<?php

namespace App\Http\Requests;

use App\Models\SpareItem;
use Illuminate\Foundation\Http\FormRequest;

class AdjustSpareRequest extends FormRequest
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
            'quantity_delta' => ['required', 'numeric', 'not_in:0', 'max:9999999999.99', 'min:-9999999999.99', 'decimal:0,2'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
