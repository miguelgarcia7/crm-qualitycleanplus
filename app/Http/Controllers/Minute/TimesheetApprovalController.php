<?php

namespace App\Http\Controllers\Minute;

use App\Domain\Billing\Actions\ApproveTimesheet;
use App\Domain\Billing\Actions\DeclineTimesheet;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Property-manager timesheet approval on the QC Minute surface. Read-only grid
 * + Approve / Decline.
 */
class TimesheetApprovalController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Timesheet::class);

        $user = Auth::user();
        $query = Timesheet::query()
            ->with('property:id,name', 'payrollPeriod:id,week_start,week_end')
            ->whereIn('status', [TimesheetStatus::PendingApproval->value, TimesheetStatus::Approved->value, TimesheetStatus::Declined->value])
            ->latest('sent_for_approval_at');

        if ($user instanceof Person && ! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            $query->whereIn('property_id', $user->assignedProperties()->pluck('properties.id')->all());
        }

        return Inertia::render('minute/timesheets/index', [
            'timesheets' => $query->get()->map(fn (Timesheet $t): array => [
                'id' => $t->id,
                'property' => $t->property?->name,
                'week_start' => $t->payrollPeriod?->week_start->toDateString(),
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
            ]),
        ]);
    }

    public function show(Timesheet $timesheet): Response
    {
        $this->authorize('view', $timesheet);

        $timesheet->load('property', 'payrollPeriod');
        $property = $timesheet->property;
        $period = $timesheet->payrollPeriod;
        $tz = $property->timezone;
        $weekStart = $period->week_start;

        return Inertia::render('minute/timesheets/show', [
            'timesheet' => [
                'id' => $timesheet->id,
                'status' => $timesheet->status->value,
                'status_label' => $timesheet->status->label(),
                'decline_reason' => $timesheet->decline_reason,
            ],
            'property' => ['id' => $property->id, 'name' => $property->name],
            'week' => [
                'start' => $weekStart->toDateString(),
                'end' => $period->week_end->toDateString(),
                'days' => collect(range(0, 6))->map(fn (int $i): string => $weekStart->addDays($i)->toDateString()),
            ],
            'rows' => $this->rows($property->id, $tz, $period->id),
            'entries' => $this->entries($period->id, $tz),
            'summaries' => $this->summaries($period->id),
            'can' => ['decide' => $timesheet->status->canDecide() && (Auth::user()?->can('approve', $timesheet) ?? false)],
        ]);
    }

    public function approve(Timesheet $timesheet, ApproveTimesheet $action): RedirectResponse
    {
        $this->authorize('approve', $timesheet);

        $user = Auth::user();
        abort_unless($user instanceof Person, 403);
        $action->handle($timesheet, $user);

        return to_route('qcminute.timesheets.index')->with('success', 'Timesheet approved.');
    }

    public function decline(Request $request, Timesheet $timesheet, DeclineTimesheet $action): RedirectResponse
    {
        $this->authorize('decline', $timesheet);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $user = Auth::user();
        abort_unless($user instanceof Person, 403);
        $action->handle($timesheet, $user, $validated['reason'], $validated['category'] ?? null);

        return to_route('qcminute.timesheets.index')->with('success', 'Timesheet declined.');
    }

    private function rows(int $propertyId, string $tz, int $periodId): Collection
    {
        return WorkOrder::query()
            ->where('property_id', $propertyId)
            ->where('status', WorkOrderStatus::Active->value)
            ->with(['person:id,name', 'position:id,name'])
            ->get()
            ->map(fn (WorkOrder $wo): array => [
                'work_order_id' => $wo->id,
                'contractor' => $wo->person?->name,
                'position' => $wo->position?->name,
            ]);
    }

    private function entries(int $periodId, string $tz): Collection
    {
        return TimeEntry::query()
            ->where('payroll_period_id', $periodId)
            ->get()
            ->map(fn (TimeEntry $e): array => [
                'id' => $e->id,
                'work_order_id' => $e->work_order_id,
                'date' => $e->start_at_utc?->copy()->setTimezone($tz)->toDateString(),
                'start_time' => $e->start_at_utc?->copy()->setTimezone($tz)->format('H:i'),
                'end_time' => $e->end_at_utc?->copy()->setTimezone($tz)->format('H:i'),
            ])
            ->values();
    }

    private function summaries(int $periodId): Collection
    {
        return TimeSummary::query()
            ->where('payroll_period_id', $periodId)
            ->get()
            ->keyBy('work_order_id')
            ->map(fn (TimeSummary $s): array => [
                'regular_minutes' => $s->regular_minutes,
                'overtime_minutes' => $s->overtime_minutes,
                'training_minutes' => $s->training_minutes,
                'total_pay' => $s->total_pay,
                'total_bill' => $s->total_bill,
            ]);
    }
}
