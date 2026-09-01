<?php

use App\Domain\Billing\Actions\ApproveTimesheet;
use App\Domain\Billing\Actions\DeclineTimesheet;
use App\Domain\Billing\Actions\GenerateInvoice;
use App\Domain\Billing\Actions\SendInvoice;
use App\Domain\Billing\Actions\SubmitTimesheetForApproval;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Events\TimeEntrySaved;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/**
 * Build a property + active WO + open payroll period (this week) + draft timesheet.
 *
 * @return array{property: Property, workOrder: WorkOrder, period: PayrollPeriod, timesheet: Timesheet, monday: Carbon}
 */
function scenario(array $rates = []): array
{
    $property = Property::factory()->create(['timezone' => 'America/Phoenix', 'tax_rate' => 0.0875]);
    $position = Position::factory()->create();
    $workOrder = WorkOrder::factory()->create(array_merge([
        'property_id' => $property->id,
        'position_id' => $position->id,
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500,
    ], $rates));

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

    return compact('property', 'workOrder', 'period', 'timesheet', 'monday');
}

function seedHours(WorkOrder $wo, Carbon $monday, int $days, string $start, string $end): void
{
    $action = app(CreateManualTimeEntry::class);
    for ($i = 0; $i < $days; $i++) {
        $action->handle($wo, [
            'date' => $monday->copy()->addDays($i)->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
            'entry_type' => 'work',
        ], null);
    }
}

it('buckets 45h into 40 regular + 5 overtime with correct amounts', function () {
    ['workOrder' => $wo, 'monday' => $monday] = scenario();

    seedHours($wo, $monday, 5, '09:00', '18:00'); // 5 × 9h = 45h

    $summary = TimeSummary::where('work_order_id', $wo->id)->first();

    expect($summary->regular_minutes)->toBe(2400)
        ->and($summary->overtime_minutes)->toBe(300)
        ->and($summary->total_pay)->toBe(95000)   // 40×$20 + 5×$30
        ->and($summary->total_bill)->toBe(142500); // 40×$30 + 5×$45
});

it('rejects a manual entry once the period is locked', function () {
    ['workOrder' => $wo, 'period' => $period, 'monday' => $monday] = scenario();
    $period->update(['status' => PayrollPeriodStatus::Locked]);

    expect(fn () => seedHours($wo, $monday, 1, '09:00', '17:00'))
        ->toThrow(ValidationException::class);
});

it('dispatches TimeEntrySaved when a punch is added via the endpoint', function () {
    Event::fake([TimeEntrySaved::class]);
    ['workOrder' => $wo, 'monday' => $monday] = scenario();

    $this->actingAs(person('office_manager'))
        ->post(main("/admin/work-orders/{$wo->id}/time-entries"), [
            'date' => $monday->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'entry_type' => 'work',
        ])
        ->assertRedirect();

    Event::assertDispatched(TimeEntrySaved::class);
});

it('corrects a punch through the endpoint and rebuilds the summary', function () {
    Event::fake([TimeEntrySaved::class]);
    ['workOrder' => $wo, 'monday' => $monday] = scenario();

    seedHours($wo, $monday, 1, '09:00', '18:00'); // 9h
    $entry = TimeEntry::where('work_order_id', $wo->id)->firstOrFail();

    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/time-entries/{$entry->id}"), [
            'start_time' => '09:00',
            'end_time' => '17:00',
            'entry_type' => 'work',
        ])
        ->assertRedirect();

    expect($entry->fresh()->duration_minutes)->toBe(480)
        ->and(TimeSummary::where('work_order_id', $wo->id)->first()->regular_minutes)->toBe(480);

    Event::assertDispatched(TimeEntrySaved::class);
});

it('runs the full pipeline: submit → approve → frozen invoice', function () {
    $s = scenario();
    seedHours($s['workOrder'], $s['monday'], 5, '09:00', '17:00'); // 40h, all regular

    $recruiter = person('recruiter');
    $s['property']->assignments()->create(['person_id' => $recruiter->id, 'role' => 'recruiter']);

    $this->actingAs($recruiter)
        ->post(main("/admin/timesheets/{$s['timesheet']->id}/submit"))
        ->assertRedirect();

    expect($s['timesheet']->fresh()->status)->toBe(TimesheetStatus::PendingApproval)
        ->and($s['period']->fresh()->status)->toBe(PayrollPeriodStatus::Locked);

    app(ApproveTimesheet::class)->handle($s['timesheet']->fresh(), person('property_manager'));

    $timesheet = $s['timesheet']->fresh();
    expect($timesheet->status)->toBe(TimesheetStatus::Invoiced)
        ->and($timesheet->invoice_id)->not->toBeNull();

    $invoice = Invoice::find($timesheet->invoice_id);
    // 40h × $30 bill = $1,200 subtotal; 8.75% tax = $105; total $1,305.
    expect($invoice->work_subtotal)->toBe(120000)
        ->and($invoice->tax_amount)->toBe(10500)
        ->and($invoice->total)->toBe(130500)
        ->and($invoice->invoice_number)->toStartWith('INV-')
        ->and($invoice->items)->toHaveCount(1);
});

it('generates invoices idempotently', function () {
    $s = scenario();
    seedHours($s['workOrder'], $s['monday'], 5, '09:00', '17:00');
    $s['timesheet']->update(['status' => TimesheetStatus::Approved]);

    $action = app(GenerateInvoice::class);
    $first = $action->handle($s['timesheet']->fresh());
    $second = $action->handle($s['timesheet']->fresh());

    expect($first->id)->toBe($second->id)
        ->and(Invoice::count())->toBe(1);
});

it('declining a timesheet reopens its period', function () {
    $s = scenario();
    seedHours($s['workOrder'], $s['monday'], 1, '09:00', '17:00');
    app(SubmitTimesheetForApproval::class)->handle($s['timesheet']->fresh(), person('recruiter'));

    app(DeclineTimesheet::class)->handle($s['timesheet']->fresh(), person('property_manager'), 'Wrong hours', 'Wrong Hours');

    expect($s['timesheet']->fresh()->status)->toBe(TimesheetStatus::Declined)
        ->and($s['period']->fresh()->status)->toBe(PayrollPeriodStatus::Open);
});

it('marks an invoice sent', function () {
    $s = scenario();
    seedHours($s['workOrder'], $s['monday'], 5, '09:00', '17:00');
    $s['timesheet']->update(['status' => TimesheetStatus::Approved]);
    $invoice = app(GenerateInvoice::class)->handle($s['timesheet']->fresh());

    app(SendInvoice::class)->handle($invoice, person('recruiter'), 'billing@hotel.test');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::InvoiceSent)
        ->and($invoice->fresh()->notification_recipient)->toBe('billing@hotel.test')
        ->and($s['timesheet']->fresh()->status)->toBe(TimesheetStatus::InvoiceSent);
});
