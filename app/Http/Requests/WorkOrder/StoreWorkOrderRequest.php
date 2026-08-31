<?php

namespace App\Http\Requests\WorkOrder;

use App\Domain\PropertyBible\Models\Property;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WorkOrder::class) ?? false;
    }

    /**
     * Rates are entered as dollars; the controller converts to cents.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'person_id' => ['required', 'integer', Rule::exists('people', 'id')->whereNull('deleted_at')],
            'property_id' => ['required', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'position_id' => [
                'required', 'integer', Rule::exists('positions', 'id')->whereNull('deleted_at'),
                // The Bible is authoritative: a WO requires a current rate for
                // this (property, position) so recruiters never invent rates.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $property = Property::find((int) $this->input('property_id'));
                    if ($property !== null && $property->currentRateFor((int) $value) === null) {
                        $fail('The Property Bible has no current rate for this position at the selected property. Add the rate on the property\'s Rates tab first.');
                    }
                },
            ],
            'pay_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'bill_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'ot_pay_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'ot_bill_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            // Hours before the property may hire this contractor directly.
            // Blank falls back to the property's contracted value.
            'direct_hire_threshold_hours' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'more_staff_request_id' => [
                'nullable', 'integer', Rule::exists('more_staff_requests', 'id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }
                    $request = MoreStaffRequest::find($value);
                    if ($request === null || ! $request->status->isOpen()) {
                        $fail('That staffing request is no longer open.');
                    } elseif ($request->property_id !== (int) $this->input('property_id')) {
                        $fail('The staffing request must be for the same property as the work order.');
                    }
                },
            ],
        ];
    }
}
