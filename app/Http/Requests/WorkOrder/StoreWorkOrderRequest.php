<?php

namespace App\Http\Requests\WorkOrder;

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
            'position_id' => ['required', 'integer', Rule::exists('positions', 'id')->whereNull('deleted_at')],
            'pay_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'bill_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'ot_pay_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'ot_bill_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
