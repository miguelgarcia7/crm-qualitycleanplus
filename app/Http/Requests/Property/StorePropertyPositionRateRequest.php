<?php

namespace App\Http\Requests\Property;

use App\Domain\PropertyBible\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyPositionRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property
            && ($this->user()?->can('editRates', $property) ?? false);
    }

    /**
     * Rates are entered as dollars in the form; the controller converts to cents.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $property = $this->route('property');
        $propertyId = $property instanceof Property ? $property->getKey() : null;

        return [
            'position_id' => ['required', 'integer', Rule::exists('positions', 'id')],
            'pay_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'bill_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'ot_pay_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'ot_bill_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'effective_date' => [
                'required',
                'date',
                Rule::unique('property_position_rates', 'effective_date')
                    ->where('property_id', $propertyId)
                    ->where('position_id', $this->integer('position_id'))
                    ->whereNull('deleted_at'),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
