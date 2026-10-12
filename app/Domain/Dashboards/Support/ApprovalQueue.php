<?php

namespace App\Domain\Dashboards\Support;

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\PropertyAssignment;
use App\Domain\Time\Models\TimeSummary;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Timesheets waiting on a property manager, oldest first: who to chase, for
 * how much, and since when. Shared by the admin's Today view (every property)
 * and the recruiter's dashboard (their own).
 */
class ApprovalQueue
{
    /** A timesheet with the property manager longer than this is overdue. */
    public const SLA_DAYS = 3;

    /**
     * @param  list<int>|null  $propertyIds  null means every property
     * @return Builder<Timesheet>
     */
    public function query(?array $propertyIds): Builder
    {
        return Timesheet::query()
            ->pendingApproval()
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds));
    }

    /**
     * @param  list<int>|null  $propertyIds
     * @return list<array<string, mixed>>
     */
    public function oldest(?array $propertyIds, CarbonImmutable $now, int $limit): array
    {
        $sheets = $this->query($propertyIds)
            ->with(['property:id,name', 'payrollPeriod:id,week_start,week_end', 'sentForApprovalBy:id,name'])
            ->orderBy('sent_for_approval_at')
            ->limit($limit)
            ->get();

        if ($sheets->isEmpty()) {
            return [];
        }

        $bills = TimeSummary::query()
            ->whereIn('payroll_period_id', $sheets->pluck('payroll_period_id'))
            ->groupBy('payroll_period_id')
            ->selectRaw('payroll_period_id, SUM(total_bill) as bill')
            ->pluck('bill', 'payroll_period_id');

        $managers = PropertyAssignment::query()
            ->whereIn('property_id', $sheets->pluck('property_id'))
            ->where('role', PropertyAssignmentRole::PropertyManager->value)
            ->with('person:id,name,email')
            ->orderBy('id')
            ->get()
            ->groupBy('property_id');

        return $sheets->map(function (Timesheet $sheet) use ($bills, $managers, $now): array {
            $period = $sheet->payrollPeriod;
            $manager = $managers->get($sheet->property_id)?->first()?->person;
            $days = $sheet->sent_for_approval_at === null ? 0 : (int) $sheet->sent_for_approval_at->diffInDays($now);

            return [
                'id' => $sheet->id,
                'property' => $sheet->property?->name,
                'week' => $period !== null ? DashboardFormat::range($period->week_start, $period->week_end) : null,
                'manager' => $manager?->name,
                'managerEmail' => $manager?->email,
                'recruiter' => $sheet->sentForApprovalBy?->name,
                'billable' => round(((int) ($bills[$sheet->payroll_period_id] ?? 0)) / 100, 2),
                'waitingDays' => $days,
                'overdue' => $days > self::SLA_DAYS,
                'href' => $period !== null
                    ? "/admin/properties/{$sheet->property_id}/grid?week={$period->week_start->toDateString()}"
                    : "/admin/properties/{$sheet->property_id}",
            ];
        })->all();
    }

    /**
     * Finished weeks that logged hours but are still draft or declined,
     * counted per property.
     *
     * @return array<int, int>
     */
    public function unsentByProperty(CarbonImmutable $today): array
    {
        return Timesheet::query()
            ->join('payroll_periods', 'payroll_periods.id', '=', 'timesheets.payroll_period_id')
            ->whereIn('timesheets.status', [TimesheetStatus::Draft->value, TimesheetStatus::Declined->value])
            ->whereDate('payroll_periods.week_end', '<', $today->toDateString())
            ->whereExists(fn ($q) => $q->from('time_summaries')
                ->whereColumn('time_summaries.payroll_period_id', 'timesheets.payroll_period_id')
                ->whereRaw('regular_minutes + overtime_minutes + holiday_minutes + training_minutes > 0'))
            ->selectRaw('timesheets.property_id, COUNT(*) as n')
            ->groupBy('timesheets.property_id')
            ->pluck('n', 'timesheets.property_id')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }
}
