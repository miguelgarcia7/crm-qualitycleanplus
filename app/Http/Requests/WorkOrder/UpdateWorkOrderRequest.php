<?php

namespace App\Http\Requests\WorkOrder;

use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workOrder = $this->route('work_order');

        return $workOrder instanceof WorkOrder
            && ($this->user()?->can('update', $workOrder) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pay_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'bill_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'ot_pay_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'ot_bill_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::enum(WorkOrderStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
