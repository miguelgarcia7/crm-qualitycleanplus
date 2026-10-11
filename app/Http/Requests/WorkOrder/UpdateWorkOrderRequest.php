<?php

namespace App\Http\Requests\WorkOrder;

use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    /**
     * Once hours are logged the rates are fixed: the form shows them read-only,
     * and a hand-made request that changes one is refused here too.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $workOrder = $this->route('work_order');
                if (! $workOrder instanceof WorkOrder || ! $workOrder->ratesLocked()) {
                    return;
                }

                foreach (['pay_rate', 'bill_rate', 'ot_pay_rate', 'ot_bill_rate'] as $field) {
                    $value = $this->input($field);
                    if (is_numeric($value) && (int) round((float) $value * 100) !== $workOrder->{$field}) {
                        $validator->errors()->add($field, 'Rates are locked once hours are logged. Use a pay increase, or a new work order, to change them.');
                    }
                }
            },
        ];
    }
}
