<?php

use App\Domain\Billing\Actions\ApproveTimesheet;
use App\Domain\Billing\Actions\SubmitTimesheetForApproval;
use App\Domain\Billing\Actions\VoidInvoice;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Reports\Actions\RefreshWeeklyRollup;
use App\Domain\Reports\Models\ReportMonthlyRevenue;
use App\Domain\Reports\Models\ReportWeeklyRollup;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/**
 * Property + active WO + open payroll period (this week) + draft timesheet.
 *
 * @return array{property: Property, position: Position, workOrder: WorkOrder, period: PayrollPeriod, timesheet: Timesheet, monday: Carbon}
 */
function reportScenario(): array
{
    $property = Property::factory()->create(['timezone' => 'America/Phoenix', 'tax_rate' => 0.10]);
    $position = Position::factory()->create();
    $workOrder = WorkOrder::factory()->create([
        'property_id' => $property->id,
        'position_id' => $position->id,
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500,
    ]);

    $monday = Carbon::now('America/Phoenix')->startOfWeek(Carbon::MONDAY);
    $period = PayrollPeriod::factory()->create([
        'property_id' => $property->id,
        'week_start' => $monday->toDateString(),
        'week_end' => $monday->copy()->addDays(6)->toDateString(),
        'status' => PayrollPeriodStatus::Open,
    ]);
    $timesheet = Timesheet::factory()->create([
        'property_id' => $property->id,
        'payroll_period_id' => $period->id,
        'status' => TimesheetStatus::Draft,
    ]);

    return compact('property', 'position', 'workOrder', 'period', 'timesheet', 'monday');
}

function reportHours(WorkOrder $workOrder, Carbon $monday, int $days, string $start = '09:00', string $end = '18:00'): void
{
    $action = app(CreateManualTimeEntry::class);
    for ($i = 0; $i < $days; $i++) {
        $action->handle($workOrder, [
            'date' => $monday->copy()->addDays($i)->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
            'entry_type' => 'work',
        ], null);
    }
}

// ── Rollup layer ────────────────────────────────────────────────────────────

it('builds the weekly rollup cell when hours land', function () {
    ['property' => $property, 'position' => $position, 'workOrder' => $wo, 'monday' => $monday] = reportScenario();

    reportHours($wo, $monday, 5); // 45h = 40 regular + 5 OT

    $cell = ReportWeeklyRollup::query()
        ->where('property_id', $property->id)
        ->where('position_id', $position->id)
        ->first();

    expect($cell)->not->toBeNull()
        ->and($cell->regular_minutes)->toBe(2400)
        ->and($cell->overtime_minutes)->toBe(300)
        ->and($cell->total_minutes)->toBe(2700)
        ->and($cell->total_pay)->toBe(95000)   // 40×$20 + 5×$30
        ->and($cell->total_bill)->toBe(142500) // 40×$30 + 5×$45
        ->and($cell->contractor_count)->toBe(1);
});

it('aggregates multiple contractors into one position cell', function () {
    ['property' => $property, 'position' => $position, 'workOrder' => $first, 'monday' => $monday] = reportScenario();
    $second = WorkOrder::factory()->create([
        'property_id' => $property->id,
        'position_id' => $position->id,
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500,
    ]);

    reportHours($first, $monday, 2, '09:00', '17:00');
    reportHours($second, $monday, 3, '09:00', '17:00');

    $cells = ReportWeeklyRollup::query()->where('property_id', $property->id)->get();

    expect($cells)->toHaveCount(1)
        ->and($cells->first()->total_minutes)->toBe(5 * 8 * 60)
        ->and($cells->first()->contractor_count)->toBe(2);
});

it('removes the weekly cell when its summaries vanish', function () {
    ['property' => $property, 'workOrder' => $wo, 'monday' => $monday] = reportScenario();
    reportHours($wo, $monday, 2);
    expect(ReportWeeklyRollup::query()->where('property_id', $property->id)->exists())->toBeTrue();

    TimeSummary::query()->where('property_id', $property->id)->delete();
    app(RefreshWeeklyRollup::class)->handle($property->id, $monday->toDateString());

    expect(ReportWeeklyRollup::query()->where('property_id', $property->id)->exists())->toBeFalse();
});

it('builds the monthly revenue cell when an invoice freezes', function () {
    ['property' => $property, 'workOrder' => $wo, 'monday' => $monday, 'timesheet' => $timesheet] = reportScenario();
    reportHours($wo, $monday, 5); // bill 142500

    $recruiter = person('recruiter');
    app(ApproveTimesheet::class)->handle(
        app(SubmitTimesheetForApproval::class)->handle($timesheet, $recruiter),
        person('property_manager'),
    );

    $invoice = Invoice::query()->firstOrFail();
    $cell = ReportMonthlyRevenue::query()
        ->where('property_id', $property->id)
        ->whereDate('month_start', $monday->copy()->startOfMonth()->toDateString())
        ->first();

    expect($cell)->not->toBeNull()
        ->and($cell->invoice_count)->toBe(1)
        ->and($cell->work_subtotal)->toBe(142500)
        ->and($cell->invoiced_total)->toBe($invoice->total)
        ->and($cell->payout_total)->toBe(95000);
});

it('retracts the monthly revenue cell when the invoice is voided', function () {
    ['workOrder' => $wo, 'monday' => $monday, 'timesheet' => $timesheet, 'property' => $property] = reportScenario();
    reportHours($wo, $monday, 5);
    app(ApproveTimesheet::class)->handle(
        app(SubmitTimesheetForApproval::class)->handle($timesheet, person('recruiter')),
        person('property_manager'),
    );
    expect(ReportMonthlyRevenue::query()->where('property_id', $property->id)->exists())->toBeTrue();

    app(VoidInvoice::class)->handle(Invoice::query()->firstOrFail(), person('super_admin'), 'Wrong rates');

    expect(ReportMonthlyRevenue::query()->where('property_id', $property->id)->exists())->toBeFalse();
});

it('rebuilds rollups from scratch via the backstop command', function () {
    ['property' => $property, 'workOrder' => $wo, 'monday' => $monday, 'timesheet' => $timesheet] = reportScenario();
    reportHours($wo, $monday, 5);
    app(ApproveTimesheet::class)->handle(
        app(SubmitTimesheetForApproval::class)->handle($timesheet, person('recruiter')),
        person('property_manager'),
    );

    ReportWeeklyRollup::query()->delete();
    ReportMonthlyRevenue::query()->delete();

    $this->artisan('reports:refresh-rollups')->assertSuccessful();

    expect(ReportWeeklyRollup::query()->where('property_id', $property->id)->count())->toBe(1)
        ->and(ReportMonthlyRevenue::query()->where('property_id', $property->id)->count())->toBe(1);
});

// ── Catalog + permission gates ──────────────────────────────────────────────

it('shows the catalog groups the viewer may open', function () {
    $this->actingAs(person('office_manager'))->get(main('/admin/reports'))
        ->assertOk()
        ->assertSee('Financial')
        ->assertSee('Operational')
        ->assertDontSee('Payouts by contractor');

    $this->actingAs(person('payroll'))->get(main('/admin/reports'))
        ->assertOk()
        ->assertSee('Payouts by contractor')
        ->assertDontSee('Hours by position');
});

it('blocks people without any report permission from the catalog', function () {
    $this->actingAs(person('front_desk'))->get(main('/admin/reports'))->assertForbidden();
});

it('gates each report by its permission', function () {
    $hr = person('hr');
    $this->actingAs($hr)->get(main('/admin/reports/hours-by-position'))->assertOk();
    $this->actingAs($hr)->get(main('/admin/reports/revenue'))->assertForbidden();
    $this->actingAs($hr)->get(main('/admin/reports/payouts'))->assertForbidden();

    $payroll = person('payroll');
    $this->actingAs($payroll)->get(main('/admin/reports/revenue'))->assertOk();
    $this->actingAs($payroll)->get(main('/admin/reports/payouts'))->assertOk();
});

it('reports invoiced revenue by month with payout totals', function () {
    ['workOrder' => $wo, 'monday' => $monday, 'timesheet' => $timesheet, 'property' => $property] = reportScenario();
    reportHours($wo, $monday, 5);
    app(ApproveTimesheet::class)->handle(
        app(SubmitTimesheetForApproval::class)->handle($timesheet, person('recruiter')),
        person('property_manager'),
    );

    $this->actingAs(person('office_manager'))->get(main('/admin/reports/revenue'))
        ->assertOk()
        ->assertSee($property->name)
        ->assertSee('142500'); // work subtotal cents in the Inertia payload
});

it('reports payouts by contractor for the latest payroll week', function () {
    ['workOrder' => $wo, 'monday' => $monday] = reportScenario();
    reportHours($wo, $monday, 5);
    $wo->property->positionCodes()->create(['position_id' => $wo->position_id, 'job_code' => '1001-10']);

    $this->actingAs(person('payroll'))->get(main('/admin/reports/payouts'))
        ->assertOk()
        ->assertSee($wo->person->name)
        ->assertSee('1001-10') // the property's job code rides along per row
        ->assertSee('95000');
});

// ── Exports ─────────────────────────────────────────────────────────────────

it('downloads report Excel exports and gates them', function () {
    ['workOrder' => $wo, 'monday' => $monday] = reportScenario();
    reportHours($wo, $monday, 5);

    $this->actingAs(person('office_manager'))
        ->get(main('/admin/reports/revenue/export'))
        ->assertOk()
        ->assertDownload();

    $this->actingAs(person('hr'))->get(main('/admin/reports/revenue/export'))->assertForbidden();

    $this->actingAs(person('payroll'))
        ->get(main('/admin/reports/payouts/export?week='.$monday->toDateString()))
        ->assertOk()
        ->assertDownload('payouts-'.$monday->toDateString().'.xlsx');
});

it('downloads the payouts PDF', function () {
    ['workOrder' => $wo, 'monday' => $monday] = reportScenario();
    reportHours($wo, $monday, 5);

    $this->actingAs(person('payroll'))
        ->get(main('/admin/reports/payouts/pdf?week='.$monday->toDateString()))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

// ── Timesheet history ───────────────────────────────────────────────────────

it('lists timesheet history with hours and invoice links', function () {
    ['workOrder' => $wo, 'monday' => $monday, 'timesheet' => $timesheet, 'property' => $property] = reportScenario();
    reportHours($wo, $monday, 5);
    app(ApproveTimesheet::class)->handle(
        app(SubmitTimesheetForApproval::class)->handle($timesheet, person('recruiter')),
        person('property_manager'),
    );

    $this->actingAs(person('office_manager'))->get(main('/admin/timesheets'))
        ->assertOk()
        ->assertSee($property->name)
        ->assertSee(Invoice::query()->firstOrFail()->invoice_number);
});

it('gates the timesheet history and its export', function () {
    $this->actingAs(person('front_desk'))->get(main('/admin/timesheets'))->assertForbidden();
    $this->actingAs(person('hr'))->get(main('/admin/timesheets/export'))->assertForbidden();

    reportScenario();
    $this->actingAs(person('recruiter'))->get(main('/admin/timesheets'))->assertOk();
    $this->actingAs(person('recruiter'))->get(main('/admin/timesheets/export'))->assertOk()->assertDownload();
});
