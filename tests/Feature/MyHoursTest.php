<?php

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Workflows\Actions\CompleteStep;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->recruiter = person('recruiter');
    $this->contractor = person('contractor');
    $this->contractor->update(['primary_recruiter_id' => $this->recruiter->id]);
});

/** A job with `$days` days of 8:00–17:00 punches `$weeksAgo` weeks back. Returns [work order, week start]. */
function myHoursJob(Person $contractor, string $position, int $weeksAgo, int $days, TimesheetStatus $status, ?Property $property = null, string $from = '08:00', string $to = '17:00'): array
{
    $property ??= Property::factory()->create(['timezone' => 'America/Chicago', 'name' => 'Acme Hotel']);
    $wo = WorkOrder::factory()->create([
        'person_id' => $contractor->id,
        'property_id' => $property->id,
        'position_id' => (Position::query()->where('name', $position)->first() ?? Position::factory()->create(['name' => $position]))->id,
        'start_date' => now()->subMonths(6)->toDateString(),
        'pay_rate' => 1800, 'ot_pay_rate' => 2700, 'bill_rate' => 2800, 'ot_bill_rate' => 4200,
    ]);

    $monday = Carbon::now('America/Chicago')->startOfWeek(Carbon::MONDAY)->subWeeks($weeksAgo);
    $period = PayrollPeriod::query()->where('property_id', $property->id)->whereDate('week_start', $monday->toDateString())->first()
        ?? PayrollPeriod::factory()->create([
            'property_id' => $property->id,
            'week_start' => $monday->toDateString(),
            'week_end' => $monday->copy()->addDays(6)->toDateString(),
            'status' => PayrollPeriodStatus::Open,
        ]);
    Timesheet::query()->firstOrCreate(['property_id' => $property->id, 'payroll_period_id' => $period->id], ['status' => TimesheetStatus::Draft]);

    foreach (range(0, $days - 1) as $day) {
        app(CreateManualTimeEntry::class)->handle($wo, [
            'date' => $monday->copy()->addDays($day)->toDateString(),
            'start_time' => $from,
            'end_time' => $to,
            'entry_type' => 'work',
        ], null);
    }

    Timesheet::query()->where('payroll_period_id', $period->id)->update(['status' => $status]);

    return [$wo, $monday->toDateString()];
}

it('lists a contractor their own weeks, newest first, with where each one stands', function () {
    myHoursJob($this->contractor, 'Houseman', 1, 5, TimesheetStatus::PendingApproval);
    myHoursJob($this->contractor, 'Houseman', 2, 5, TimesheetStatus::InvoiceSent);
    myHoursJob($this->contractor, 'Houseman', 20, 5, TimesheetStatus::InvoiceSent); // older than 3 months

    $response = $this->actingAs($this->contractor)
        ->get(qcminute('/my-hours'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('minute/my-hours/index')
            ->has('weeks', 2)
            ->where('weeks.0.status', 'waiting')
            ->where('weeks.1.status', 'approved')
            // 45 h: 40 × $18 + 5 × $27, twice
            ->where('weeks.0.gross', 85500)
            ->where('summary.minutes', 90 * 60)
            ->where('summary.overtime_minutes', 10 * 60)
            ->where('summary.ot_rate', 2700)
            ->where('filters.range', '3m'));

    // Their own pay only: the bill rate never leaves the server.
    expect(json_encode($response->viewData('page')['props']))->not->toContain('2800')->not->toContain('bill');

    $this->actingAs($this->contractor)
        ->get(qcminute('/my-hours?range=all'))
        ->assertInertia(fn (Assert $page) => $page->has('weeks', 3));
});

it('filters to one job when a contractor has more than one', function () {
    $hotel = Property::factory()->create(['timezone' => 'America/Chicago']);
    [$housekeeping] = myHoursJob($this->contractor, 'Housekeeper', 1, 4, TimesheetStatus::InvoiceSent, $hotel);
    myHoursJob($this->contractor, 'Bartender', 1, 3, TimesheetStatus::InvoiceSent, $hotel, '18:00', '23:00');

    $this->actingAs($this->contractor)
        ->get(qcminute("/my-hours?job={$housekeeping->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->has('jobs', 2)
            ->where('filters.job', $housekeeping->id)
            ->has('weeks.0.jobs', 1)
            ->where('weeks.0.minutes', 4 * 9 * 60));
});

it('shows one week day by day in the hotel’s time', function () {
    [, $week] = myHoursJob($this->contractor, 'Houseman', 1, 2, TimesheetStatus::PendingApproval);

    $this->actingAs($this->contractor)
        ->get(qcminute("/my-hours/{$week}"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('minute/my-hours/show')
            ->where('week.start', $week)
            ->has('days', 7)
            ->where('days.0.punches.0.in', '8:00 am')
            ->where('days.0.punches.0.out', '5:00 pm')
            ->where('days.0.punches.0.how', 'Added by the office')
            ->where('days.0.minutes', 9 * 60)
            ->where('days.2.punches', [])
            ->where('recruiter', $this->recruiter->name)
            ->where('can_report', true));

    // A week with no hours of theirs isn't there.
    $this->actingAs($this->contractor)->get(qcminute('/my-hours/2020-01-06'))->assertNotFound();
});

it('keeps My hours to contractors', function (string $role) {
    $this->actingAs(person($role))->get(qcminute('/my-hours'))->assertForbidden();
})->with(['property_manager']);

it('sends a reported week to the contractor’s recruiter, and tells the contractor when it’s resolved', function () {
    [$wo, $week] = myHoursJob($this->contractor, 'Houseman', 1, 5, TimesheetStatus::PendingApproval);

    $this->actingAs($this->contractor)
        ->post(qcminute("/my-hours/{$week}/report"), ['message' => 'I clocked out at 5:30 on Tuesday.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $workflow = Workflow::query()->where('type', WorkflowType::HoursProblem->value)->sole();
    $step = WorkflowStep::query()->where('workflow_id', $workflow->id)->sole();
    expect($step->assigned_to)->toBe($this->recruiter->id)
        ->and($workflow->data['work_order_id'])->toBe($wo->id)
        ->and($this->recruiter->notifications()->count())->toBe(1);

    // My Tasks shows the recruiter what it's about.
    $this->actingAs($this->recruiter)
        ->get(main('/admin/tasks'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tasks.0.workflow_type', 'Hours Problem')
            ->where('tasks.0.summary', fn (string $s) => str_contains($s, $this->contractor->name) && str_contains($s, 'Acme Hotel') && str_contains($s, '5:30 on Tuesday')));

    // A second report while the first is open is turned away.
    $this->actingAs($this->contractor)
        ->post(qcminute("/my-hours/{$week}/report"), ['message' => 'Again'])
        ->assertSessionHasErrors('message');

    app(CompleteStep::class)->handle($step, $this->recruiter, 'Fixed Tuesday’s clock-out.');

    expect($workflow->fresh()->status)->toBe(WorkflowStatus::Completed)
        ->and($this->contractor->notifications()->latest()->first()->data['message'])->toContain('resolved')->toContain('Fixed Tuesday');

    $this->actingAs($this->contractor)
        ->get(qcminute("/my-hours/{$week}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reports.0.status', 'completed')
            ->where('reports.0.reply', 'Fixed Tuesday’s clock-out.')
            ->where('can_report', true));
});

it('sends a report to the office managers when the contractor has no recruiter', function () {
    $this->contractor->update(['primary_recruiter_id' => null]);
    [, $week] = myHoursJob($this->contractor, 'Houseman', 1, 5, TimesheetStatus::PendingApproval);

    $this->actingAs($this->contractor)
        ->post(qcminute("/my-hours/{$week}/report"), ['message' => 'Monday is missing.'])
        ->assertSessionHasNoErrors();

    expect(WorkflowStep::query()->sole())
        ->assigned_to->toBeNull()
        ->assigned_role->toBe('office_manager');
});
