<?php

use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Enums\ChargeReason;
use App\Domain\Adjustments\Models\ContractorChargeSchedule;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Dashboards\Services\PayrollHome;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    // Monday Oct 12, 9 am in Chicago: paying the week of Sep 28 – Oct 4.
    $this->travelTo(CarbonImmutable::parse('2026-10-12 14:00:00', 'UTC'));
});

function payroll(?string $week = null): array
{
    return app(PayrollHome::class)->build(person('payroll'), $week);
}

/** A property's pay week with its timesheet, and one placement's hours in it. */
function payWeek(Property $property, string $start, TimesheetStatus $status, array $summary = []): TimeSummary
{
    $period = PayrollPeriod::factory()->create(['property_id' => $property->id, 'week_start' => $start, 'week_end' => CarbonImmutable::parse($start)->addDays(6)->toDateString()]);
    Timesheet::factory()->create(['property_id' => $property->id, 'payroll_period_id' => $period->id, 'status' => $status]);
    $workOrder = WorkOrder::factory()->create(['property_id' => $property->id, 'start_date' => '2026-01-05']);

    return TimeSummary::factory()->create([
        'work_order_id' => $workOrder->id,
        'person_id' => $workOrder->person_id,
        'property_id' => $property->id,
        'payroll_period_id' => $period->id,
        'week_start' => $start,
        'week_end' => CarbonImmutable::parse($start)->addDays(6)->toDateString(),
        'regular_minutes' => 2400,
        'total_pay' => 800_00,
        ...$summary,
    ]);
}

it('gives payroll its own dashboard', function () {
    $this->actingAs(person('payroll'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/dashboard/payroll')
            ->where('home.run.label', 'Sep 28 – Oct 4')
            ->where('home.run.isDefault', true));
});

it('pays the week before last by default, and steps to other weeks but never ahead of last week', function () {
    expect(payroll()['run'])->toMatchArray(['start' => '2026-09-28', 'previous' => '2026-09-21', 'next' => '2026-10-05'])
        ->and(payroll('2026-10-05')['run'])->toMatchArray(['start' => '2026-10-05', 'next' => null, 'isDefault' => false])
        ->and(payroll('2026-10-12')['run']['start'])->toBe('2026-09-28')   // this week isn't finished
        ->and(payroll('2026-09-16')['run']['start'])->toBe('2026-09-14');  // any day snaps to its Monday
});

it('adds up the run across properties whose pay weeks end in it, and says who is not approved yet', function () {
    $acme = Property::factory()->create();
    $harbor = Property::factory()->create();
    payWeek($acme, '2026-09-28', TimesheetStatus::Approved, ['overtime_minutes' => 300, 'total_pay' => 900_00]);
    payWeek($harbor, '2026-09-26', TimesheetStatus::PendingApproval, ['holiday_minutes' => 480]); // Sat–Fri week ending Oct 2
    payWeek($acme, '2026-10-05', TimesheetStatus::Draft, ['total_pay' => 5000_00]);                // next run

    $run = payroll()['run'];

    expect($run['gross'])->toBe(1700.0)
        ->and($run['contractors'])->toBe(2)
        ->and($run['hours'])->toBe(['regular' => 80.0, 'overtime' => 5.0, 'holiday' => 8.0, 'training' => 0.0])
        ->and($run['propertiesWorked'])->toBe(2)
        ->and($run['propertiesApproved'])->toBe(1)
        ->and($run['notApproved'])->toBe(['properties' => 1, 'contractors' => 1]);
});

it('splits deductions into hiring fee, uniforms and the rest, with incentives added back', function () {
    $summary = payWeek(Property::factory()->create(), '2026-09-28', TimesheetStatus::Approved);
    $adjust = fn (AdjustmentType $type, int $cents) => TimeEntryAdjustment::create([
        'person_id' => $summary->person_id,
        'property_id' => $summary->property_id,
        'payroll_period_id' => $summary->payroll_period_id,
        'source_type' => 'manual',
        'value' => $cents,
        'type' => $type,
        'is_billable' => false,
    ]);

    $fee = $adjust(AdjustmentType::Deduction, 50_00);
    $schedule = ContractorChargeSchedule::factory()->create(['person_id' => $summary->person_id, 'reason' => ChargeReason::HiringFee]);
    $schedule->entries()->create(['payroll_period_id' => $summary->payroll_period_id, 'amount' => 50_00, 'payment_index' => 1, 'status' => 'applied', 'applied_adjustment_id' => $fee->id]);
    $adjust(AdjustmentType::Deduction, 120_00);
    $adjust(AdjustmentType::Incentive, 75_00);

    $adjustments = payroll()['run']['adjustments'];

    expect($adjustments['net'])->toBe(-95.0)
        ->and($adjustments['lines'])->toBe([
            ['key' => 'hiringFee', 'label' => 'Hiring fee installments', 'count' => 1, 'amount' => -50.0],
            ['key' => 'other', 'label' => 'Other deductions', 'count' => 1, 'amount' => -120.0],
            ['key' => 'incentives', 'label' => 'Incentives', 'count' => 1, 'amount' => 75.0],
        ]);
});

it('flags big overtime, long shifts, hand-edited punches and first weeks', function () {
    $property = Property::factory()->create();
    $overtime = payWeek($property, '2026-09-28', TimesheetStatus::Approved, ['overtime_minutes' => 16 * 60, 'total_pay' => 1189_55]);
    TimeSummary::factory()->create(['work_order_id' => $overtime->work_order_id, 'person_id' => $overtime->person_id, 'property_id' => $property->id, 'week_start' => '2026-09-21', 'week_end' => '2026-09-27', 'regular_minutes' => 2400]);

    TimeEntry::factory()->create([
        'work_order_id' => $overtime->work_order_id,
        'person_id' => $overtime->person_id,
        'property_id' => $property->id,
        'payroll_period_id' => $overtime->payroll_period_id,
        'start_at_utc' => '2026-10-01 13:00:00',
        'end_at_utc' => '2026-10-02 03:00:00',
        'duration_minutes' => 14 * 60,
        'was_updated' => true,
        'timezone' => 'America/Chicago',
    ]);

    $checks = payroll()['checks'];

    expect(collect($checks['rows'])->pluck('what')->all())->toBe(['16 h overtime', '14 h shift on Oct 1', 'Punch edited by hand'])
        ->and($checks['rows'][0]['pay'])->toBe(1189.55);

    // A placement whose first hours are in this run.
    payWeek(Property::factory()->create(), '2026-09-28', TimesheetStatus::Approved);
    expect(collect(payroll()['checks']['rows'])->pluck('what'))->toContain('First week');
});

it('lists overdue invoices oldest first with the amount owed', function () {
    Invoice::factory()->create(['status' => InvoiceStatus::InvoiceSent, 'due_date' => '2026-08-09', 'total' => 1400_00, 'invoice_number' => 'INV-OLD']);
    Invoice::factory()->create(['status' => InvoiceStatus::InvoiceSent, 'due_date' => '2026-10-07', 'total' => 1980_00]);
    Invoice::factory()->create(['status' => InvoiceStatus::InvoiceSent, 'due_date' => '2026-08-01', 'paid_at' => now()]);
    Invoice::factory()->create(['status' => InvoiceStatus::InvoiceSent, 'due_date' => '2026-11-01']);

    $overdue = payroll()['overdue'];

    expect($overdue['total'])->toBe(2)
        ->and($overdue['amount'])->toBe(3380.0)
        ->and($overdue['rows'][0])->toMatchArray(['number' => 'INV-OLD', 'daysLate' => 64]);
});

it('shows staff time off in this run and coming up', function () {
    PtoRequest::factory()->create(['status' => PtoRequestStatus::Approved, 'start_date' => '2026-10-02', 'end_date' => '2026-10-02']);
    PtoRequest::factory()->create(['status' => PtoRequestStatus::Approved, 'start_date' => '2026-10-20', 'end_date' => '2026-10-24']);
    PtoRequest::factory()->create(['status' => PtoRequestStatus::Approved, 'start_date' => '2026-09-01', 'end_date' => '2026-09-02']);

    expect(collect(payroll()['timeOff']['rows'])->pluck('when')->all())->toBe(['this run', 'upcoming']);
});
