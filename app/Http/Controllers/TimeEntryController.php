<?php

namespace App\Http\Controllers;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Actions\DeleteTimeEntry;
use App\Domain\Time\Events\TimeEntrySaved;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Http\Requests\Time\StoreTimeEntryRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TimeEntryController extends Controller
{
    /** The live weekly grid for a property (contractors × days). */
    public function grid(Request $request, Property $property): Response
    {
        $this->authorizeProperty($property, 'time_entries.view');

        $tz = $property->timezone;
        $weekStart = $request->filled('week')
            ? CarbonImmutable::parse($request->string('week')->value(), $tz)->startOfWeek(CarbonImmutable::MONDAY)
            : CarbonImmutable::now($tz)->startOfWeek(CarbonImmutable::MONDAY);

        $days = collect(range(0, 6))->map(fn (int $i): string => $weekStart->addDays($i)->toDateString());

        $period = PayrollPeriod::query()
            ->where('property_id', $property->id)
            ->whereDate('week_start', $weekStart->toDateString())
            ->first();

        $workOrders = $property->workOrders()
            ->where('status', WorkOrderStatus::Active->value)
            ->with(['person:id,name', 'position:id,name'])
            ->get();

        $entries = $period === null ? collect() : TimeEntry::query()
            ->where('payroll_period_id', $period->id)
            ->get()
            ->map(fn (TimeEntry $e): array => [
                'id' => $e->id,
                'work_order_id' => $e->work_order_id,
                'date' => $e->start_at_utc?->copy()->setTimezone($tz)->toDateString(),
                'start_time' => $e->start_at_utc?->copy()->setTimezone($tz)->format('H:i'),
                'end_time' => $e->end_at_utc?->copy()->setTimezone($tz)->format('H:i'),
                'duration_minutes' => $e->duration_minutes,
                'entry_type' => $e->entry_type->value,
            ]);

        $summaries = $period === null ? collect() : TimeSummary::query()
            ->where('payroll_period_id', $period->id)
            ->get()
            ->keyBy('work_order_id')
            ->map(fn (TimeSummary $s): array => [
                'regular_minutes' => $s->regular_minutes,
                'overtime_minutes' => $s->overtime_minutes,
                'training_minutes' => $s->training_minutes,
                'total_pay' => $s->total_pay,
                'total_bill' => $s->total_bill,
            ]);

        $user = Auth::user();
        $timesheet = $period?->timesheet;

        return Inertia::render('admin/timesheets/grid', [
            'property' => ['id' => $property->id, 'name' => $property->name, 'timezone' => $tz],
            'week' => ['start' => $weekStart->toDateString(), 'end' => $weekStart->addDays(6)->toDateString(), 'days' => $days],
            'period' => $period === null ? null : ['id' => $period->id, 'status' => $period->status->value],
            'timesheet' => $timesheet === null ? null : [
                'id' => $timesheet->id,
                'status' => $timesheet->status->value,
                'status_label' => $timesheet->status->label(),
                'decline_reason' => $timesheet->decline_reason,
            ],
            'rows' => $workOrders->map(fn (WorkOrder $wo): array => [
                'work_order_id' => $wo->id,
                'contractor' => $wo->person?->name,
                'position' => $wo->position?->name,
            ]),
            'entries' => $entries->values(),
            'summaries' => $summaries,
            'can' => [
                'edit' => $user instanceof Person && $user->can('time_entries.create_manual')
                    && ($period?->status->isEditable() ?? false),
                'submit' => $user instanceof Person && $user->can('timesheets.submit_for_approval')
                    && ($timesheet?->status->canSubmit() ?? false),
            ],
        ]);
    }

    public function store(StoreTimeEntryRequest $request, WorkOrder $workOrder, CreateManualTimeEntry $action): RedirectResponse
    {
        $this->authorizeProperty($workOrder->property, 'time_entries.create_manual');

        $entry = $action->handle($workOrder, $request->validated(), $request->user());

        TimeEntrySaved::dispatch($entry->property_id, $entry->payrollPeriod->week_start->toDateString());

        return back()->with('success', 'Time entry added.');
    }

    public function destroy(TimeEntry $timeEntry, DeleteTimeEntry $action): RedirectResponse
    {
        $this->authorizeProperty($timeEntry->property, 'time_entries.delete');

        $propertyId = $timeEntry->property_id;
        $weekStart = $timeEntry->payrollPeriod->week_start->toDateString();

        $action->handle($timeEntry);

        TimeEntrySaved::dispatch($propertyId, $weekStart);

        return back()->with('success', 'Time entry removed.');
    }

    /** Permission + property "(own)" scoping (recruiter/PM see only assigned). */
    private function authorizeProperty(?Property $property, string $permission): void
    {
        $user = Auth::user();
        abort_unless($user instanceof Person && $user->can($permission), 403);
        abort_unless($property !== null, 404);

        if ($user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            return;
        }

        abort_unless($user->isAssignedTo($property), 403);
    }
}
