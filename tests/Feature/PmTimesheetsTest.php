<?php

use App\Domain\Billing\Actions\ApproveTimesheet;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/**
 * A property with its manager and recruiter, one contractor, and a week of
 * hours on it, `$weeksAgo` weeks back.
 *
 * @return array{property: Property, pm: Person, recruiter: Person, workOrder: WorkOrder, period: PayrollPeriod, timesheet: Timesheet, monday: Carbon}
 */
function pmWeek(int $weeksAgo = 1, ?Property $property = null, ?Person $pm = null, ?WorkOrder $workOrder = null): array
{
    $property ??= Property::factory()->create(['timezone' => 'America/Chicago', 'tax_rate' => 0.0625]);
    $pm ??= tap(person('property_manager'), fn (Person $p) => $property->assignments()->create(['person_id' => $p->id, 'role' => 'property_manager']));
    $recruiter = person('recruiter');
    $workOrder ??= WorkOrder::factory()->create([
        'property_id' => $property->id,
        'position_id' => Position::factory()->create()->id,
        'start_date' => now()->subMonths(2)->toDateString(),
        'pay_rate' => 1800, 'bill_rate' => 2700, 'ot_pay_rate' => 2700, 'ot_bill_rate' => 4050,
    ]);

    $monday = Carbon::now('America/Chicago')->startOfWeek(Carbon::MONDAY)->subWeeks($weeksAgo);
    $period = PayrollPeriod::factory()->create([
        'property_id' => $property->id,
        'week_start' => $monday->toDateString(),
        'week_end' => $monday->copy()->addDays(6)->toDateString(),
        'status' => PayrollPeriodStatus::Open,
    ]);
    $timesheet = Timesheet::factory()->create(['property_id' => $property->id, 'payroll_period_id' => $period->id, 'status' => TimesheetStatus::Draft]);

    foreach (range(0, 4) as $day) {
        app(CreateManualTimeEntry::class)->handle($workOrder, [
            'date' => $monday->copy()->addDays($day)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'entry_type' => 'work',
        ], null);
    }

    $timesheet->update(['status' => TimesheetStatus::PendingApproval, 'sent_for_approval_at' => now()->subDays(2), 'sent_for_approval_by' => $recruiter->id]);

    return compact('property', 'pm', 'recruiter', 'workOrder', 'period', 'timesheet', 'monday');
}

it('lists every week sent to the manager, with its hours and amount, and keeps drafts out', function () {
    $last = pmWeek(1);
    $older = pmWeek(2, $last['property'], $last['pm'], $last['workOrder']);
    app(ApproveTimesheet::class)->handle($older['timesheet'], $last['pm']);
    $draft = pmWeek(3, $last['property'], $last['pm'], $last['workOrder']);
    $draft['timesheet']->update(['status' => TimesheetStatus::Draft]);
    pmWeek(1); // another property, another manager

    $this->actingAs($last['pm'])
        ->get(qcminute('/timesheets'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('minute/timesheets/index')
            ->has('timesheets', 2)
            ->where('timesheets.0.id', $last['timesheet']->id)
            ->where('timesheets.0.status', 'pending_approval')
            ->where('timesheets.0.total_minutes', 45 * 60)
            ->where('timesheets.0.overtime_minutes', 5 * 60)
            // 40 × $27 + 5 × $40.50
            ->where('timesheets.0.amount', 128250)
            ->where('timesheets.0.waiting_days', 2)
            ->where('timesheets.1.id', $older['timesheet']->id)
            ->where('timesheets.1.status', 'invoiced')
            ->where('timesheets.1.approved_by', 'you')
            ->where('timesheets.1.invoice_number', $older['timesheet']->fresh()->invoice->invoice_number)
            ->has('waiting', 1)
            ->where('waiting.0.id', $last['timesheet']->id)
            ->where('counts', ['all' => 2, 'waiting' => 1, 'declined' => 0, 'invoiced' => 1])
            ->has('this_week', 1));
});

it('narrows the list by status', function () {
    $last = pmWeek(1);
    $older = pmWeek(2, $last['property'], $last['pm'], $last['workOrder']);
    app(ApproveTimesheet::class)->handle($older['timesheet'], $last['pm']);

    $this->actingAs($last['pm'])
        ->get(qcminute('/timesheets?status=invoiced'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('timesheets', 1)
            ->where('timesheets.0.id', $older['timesheet']->id)
            ->where('filters.status', 'invoiced'));
});

it('never shows the manager pay or rates on the week', function () {
    $s = pmWeek(1);

    $response = $this->actingAs($s['pm'])->get(qcminute("/timesheets/{$s['timesheet']->id}"));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('minute/timesheets/show')
        ->where("summaries.{$s['workOrder']->id}.total_bill", 128250)
        ->missing("summaries.{$s['workOrder']->id}.total_pay")
        ->missing('rows.0.pay_rate')
        ->missing('rows.0.bill_rate')
        ->where('can.decide', true));

    $props = json_encode($response->viewData('page')['props']);
    expect($props)->not->toContain('pay_rate')
        ->and($props)->not->toContain('total_pay')
        ->and($props)->not->toContain('bill_rate');
});

it('keeps a contractor whose work order closed during the week', function () {
    $s = pmWeek(1);
    $s['workOrder']->update(['status' => WorkOrderStatus::Closed, 'end_date' => $s['monday']->copy()->addDays(4)->toDateString()]);

    $this->actingAs($s['pm'])
        ->get(qcminute("/timesheets/{$s['timesheet']->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows', 1)
            ->where('rows.0.work_order_id', $s['workOrder']->id));
});

it('approves a week back onto the week, which then links its invoice', function () {
    $s = pmWeek(1);

    $this->actingAs($s['pm'])
        ->post(qcminute("/timesheets/{$s['timesheet']->id}/approve"))
        ->assertRedirect(qcminute("/timesheets/{$s['timesheet']->id}"));

    $timesheet = $s['timesheet']->fresh();
    expect($timesheet->status)->toBe(TimesheetStatus::Invoiced);

    $this->actingAs($s['pm'])
        ->get(qcminute("/timesheets/{$timesheet->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('timesheet.approved_by', 'you')
            ->where('timesheet.invoice.number', $timesheet->invoice->invoice_number)
            ->where('can.decide', false));
});

it('declines a week with a reason and shows it on the week', function () {
    $s = pmWeek(1);

    $this->actingAs($s['pm'])
        ->post(qcminute("/timesheets/{$s['timesheet']->id}/decline"), ['reason' => 'Tuesday is short an hour', 'category' => 'Wrong Hours'])
        ->assertRedirect(qcminute("/timesheets/{$s['timesheet']->id}"));

    $this->actingAs($s['pm'])
        ->get(qcminute("/timesheets/{$s['timesheet']->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('timesheet.status', 'declined')
            ->where('timesheet.declined_by', 'you')
            ->where('timesheet.decline_reason', 'Tuesday is short an hour'));
});

it('shows the week in progress at the manager\'s own property only', function () {
    $mine = pmWeek(0);
    $mine['timesheet']->update(['status' => TimesheetStatus::Draft]);
    $theirs = pmWeek(0);

    $this->actingAs($mine['pm'])
        ->get(qcminute("/timesheets/this-week?property={$theirs['property']->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('minute/timesheets/show')
            ->where('property.id', $mine['property']->id)
            ->where('week.start', $mine['monday']->toDateString())
            ->where('week.is_current', true)
            ->where('can.decide', false));
});

it('has no week in progress for a manager without a property', function () {
    $this->actingAs(person('property_manager'))
        ->get(qcminute('/timesheets/this-week'))
        ->assertNotFound();
});

it('keeps another property\'s week out of reach', function () {
    $mine = pmWeek(1);
    $theirs = pmWeek(1);

    $this->actingAs($mine['pm'])
        ->get(qcminute("/timesheets/{$theirs['timesheet']->id}"))
        ->assertForbidden();
});
