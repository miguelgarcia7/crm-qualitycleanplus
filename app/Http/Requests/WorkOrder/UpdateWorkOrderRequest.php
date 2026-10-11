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
        $workOrder = $this->route('work_order');

        // Closing goes through the Close action, which records the end date
        // and logs it; the form may only keep a work order that is already
        // closed as closed (or reopen it).
        $statuses = [WorkOrderStatus::Active, WorkOrderStatus::Suspended];
        if ($workOrder instanceof WorkOrder && $workOrder->status === WorkOrderStatus::Closed) {
            $statuses[] = WorkOrderStatus::Closed;
        }

        return [
            'pay_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'bill_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'ot_pay_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'ot_bill_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::enum(WorkOrderStatus::class)->only($statuses)],
            // Hours before the property may hire this contractor directly.
            // Blank leaves the work order's current value as it is.
            'direct_hire_threshold_hours' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
