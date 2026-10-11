<?php

namespace App\Domain\WorkOrders\Support;

use App\Domain\People\Models\Person;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;

/**
 * Everything the Work Orders list's side panel shows for one placement: its
 * rates against the property's current Bible rate, progress toward direct
 * hire, the last few weeks of hours, how it came about, and what the viewer
 * may do with it. Loaded only when a row is opened.
 */
class WorkOrderPanel
{
    private const RECENT_WEEKS = 4;

    public function __construct(private readonly DirectHireProgress $directHire) {}

    /**
     * @return array<string, mixed>
     */
    public function build(WorkOrder $workOrder, Person $viewer): array
    {
        $workOrder->loadMissing([
            'person:id,name,email,phone',
            'property:id,name,timezone,closing_day',
            'position:id,name',
            'createdBy:id,name',
            'parent:id,source,start_date',
            'replacements:id,parent_wo_id,source,start_date',
        ]);

        $bible = $workOrder->property->currentRateFor($workOrder->position_id);
        $active = $workOrder->status === WorkOrderStatus::Active;

        return [
            'id' => $workOrder->id,
            'status' => $workOrder->status->value,
            'status_label' => $workOrder->status->label(),
            'is_temporary_assignment' => $workOrder->is_temporary_assignment,
            'person_id' => $workOrder->person_id,
            'contractor' => $workOrder->person?->name,
            'email' => $workOrder->person?->email,
            'phone' => $workOrder->person?->phone,
            'property_id' => $workOrder->property_id,
            'property' => $workOrder->property?->name,
            'position_id' => $workOrder->position_id,
            'position' => $workOrder->position?->name,
            'start_date' => $workOrder->start_date->toDateString(),
            'end_date' => $workOrder->end_date?->toDateString(),
            'pay_rate' => $workOrder->pay_rate,
            'bill_rate' => $workOrder->bill_rate,
            'ot_pay_rate' => $workOrder->ot_pay_rate,
            'ot_bill_rate' => $workOrder->ot_bill_rate,
            // What the property's Bible says this position pays today, so a
            // placement above or below it stands out.
            'bible_rate' => $bible === null ? null : ['pay_rate' => $bible->pay_rate, 'bill_rate' => $bible->bill_rate],
            'direct_hire' => $this->directHire->for($workOrder),
            'weeks' => $this->recentWeeks($workOrder),
            // The pay week in progress at the property, so the panel can mark it.
            'this_week_start' => $workOrder->property->weekStartFor(CarbonImmutable::now($workOrder->property->timezone))->toDateString(),
            'history' => $this->history($workOrder),
            'can' => [
                'edit' => $viewer->can('update', $workOrder),
                'close' => $workOrder->status !== WorkOrderStatus::Closed && $viewer->can('close', $workOrder),
                'transfer' => $active && $viewer->can('workflows.transfer.initiate'),
                'temp' => $active && ! $workOrder->is_temporary_assignment && $viewer->can('workflows.temporary_assignment.initiate'),
            ],
        ];
    }

    /**
     * The latest weeks with time on this work order, newest first. Hours
     * count every bucket the grid counts.
     *
     * @return list<array{week_start: string, week_end: string, total_minutes: int, overtime_minutes: int}>
     */
    private function recentWeeks(WorkOrder $workOrder): array
    {
        return TimeSummary::query()
            ->where('work_order_id', $workOrder->id)
            ->orderByDesc('week_start')
            ->limit(self::RECENT_WEEKS)
            ->get()
            ->map(fn (TimeSummary $s): array => [
                'week_start' => $s->week_start->toDateString(),
                'week_end' => $s->week_end->toDateString(),
                'total_minutes' => $s->regular_minutes + $s->overtime_minutes + $s->holiday_minutes + $s->training_minutes,
                'overtime_minutes' => $s->overtime_minutes,
            ])
            ->values()
            ->all();
    }

    /**
     * How the placement came about and what followed it, oldest first: the
     * work order it continues (a transfer or pay increase), its start, the
     * ones that replaced it, and its end.
     *
     * @return list<array{date: string, text: string, work_order_id: int|null}>
     */
    private function history(WorkOrder $workOrder): array
    {
        $events = [];
        $by = $workOrder->createdBy?->name;

        if ($workOrder->parent !== null) {
            $events[] = [
                'date' => $workOrder->start_date->toDateString(),
                'text' => match ($workOrder->source) {
                    WorkOrderSource::PayIncrease => 'Pay increase from work order',
                    WorkOrderSource::Transfer => 'Transferred from work order',
                    WorkOrderSource::TemporaryAssignment => 'Temporary assignment from work order',
                    default => 'Continues work order',
                },
                'work_order_id' => $workOrder->parent->id,
            ];
        } else {
            $events[] = [
                'date' => $workOrder->start_date->toDateString(),
                'text' => 'Placed as '.$workOrder->position?->name.($by ? " by {$by}" : '').($workOrder->source === WorkOrderSource::Imported ? ' (imported)' : ''),
                'work_order_id' => null,
            ];
        }

        foreach ($workOrder->replacements->sortBy('start_date') as $replacement) {
            $events[] = [
                'date' => $replacement->start_date->toDateString(),
                'text' => match ($replacement->source) {
                    WorkOrderSource::PayIncrease => 'Pay increase: continued on work order',
                    WorkOrderSource::Transfer => 'Transferred: continued on work order',
                    WorkOrderSource::TemporaryAssignment => 'Temporary assignment on work order',
                    default => 'Continued on work order',
                },
                'work_order_id' => $replacement->id,
            ];
        }

        if ($workOrder->status === WorkOrderStatus::Closed && $workOrder->end_date !== null) {
            $events[] = ['date' => $workOrder->end_date->toDateString(), 'text' => 'Closed', 'work_order_id' => null];
        }

        return collect($events)->sortBy('date')->values()->all();
    }
}
