<?php

namespace App\Http\Requests;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeterReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('asset');

        if (! $asset instanceof Asset) {
            return false;
        }

        return $this->user()?->can('recordReading', $asset) ?? false;
    }

    public function rules(): array
    {
        return [
            'reading_hours' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'reading_at' => ['required', 'date', 'before_or_equal:now'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reading_hours.decimal' => 'Running hours must use at most two decimal places.',
            'reading_at.before_or_equal' => 'The reading timestamp cannot be in the future.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $asset = $this->route('asset');

            if ($asset instanceof Asset && ! $asset->has_hour_meter) {
                $v->errors()->add('reading_hours', 'This asset does not have a running-hour meter.');
            }
        });
    }
}
