<?php

use App\Domain\Billing\Actions\GenerateInvoice;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** @return array{property: Property, position: Position} */
function jobCodeScenario(): array
{
    $property = Property::factory()->create();
    $position = Position::factory()->create(['name' => 'Housekeeper']);
    $property->positionRates()->create([
        'position_id' => $position->id,
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500,
        'effective_date' => now()->subMonth()->toDateString(), 'is_active' => true,
    ]);

    return compact('property', 'position');
}

it('saves, updates, and clears job codes per position', function () {
    ['property' => $property, 'position' => $position] = jobCodeScenario();

    $this->actingAs(person('office_manager'))
        ->put(main("/admin/properties/{$property->id}/job-codes"), [
            'codes' => [['position_id' => $position->id, 'job_code' => '1001-10']],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($property->jobCodesByPosition())->toBe([$position->id => '1001-10']);

    // Update in place.
    $this->actingAs(person('office_manager'))
        ->put(main("/admin/properties/{$property->id}/job-codes"), [
            'codes' => [['position_id' => $position->id, 'job_code' => '5500-01']],
        ]);
    expect($property->jobCodesByPosition())->toBe([$position->id => '5500-01']);

    // Blank clears.
    $this->actingAs(person('office_manager'))
        ->put(main("/admin/properties/{$property->id}/job-codes"), [
            'codes' => [['position_id' => $position->id, 'job_code' => '']],
        ]);
    expect($property->jobCodesByPosition())->toBe([]);
});

it('leaves positions that were not submitted untouched', function () {
    ['property' => $property, 'position' => $position] = jobCodeScenario();
    $other = Position::factory()->create(['name' => 'Cook']);
    $property->positionCodes()->create(['position_id' => $other->id, 'job_code' => 'KEEP-1']);

    $this->actingAs(person('office_manager'))
        ->put(main("/admin/properties/{$property->id}/job-codes"), [
            'codes' => [['position_id' => $position->id, 'job_code' => 'NEW-2']],
        ]);

    $codes = $property->jobCodesByPosition();
    ksort($codes);
    expect($codes)->toBe([$position->id => 'NEW-2', $other->id => 'KEEP-1']);
});

it('forbids a recruiter from editing job codes', function () {
    ['property' => $property, 'position' => $position] = jobCodeScenario();

    $this->actingAs(person('recruiter'))
        ->put(main("/admin/properties/{$property->id}/job-codes"), [
            'codes' => [['position_id' => $position->id, 'job_code' => 'X']],
        ])
        ->assertForbidden();
});

it('freezes the job code onto invoice items and the position summary', function () {
    ['property' => $property, 'position' => $position] = jobCodeScenario();
    $property->positionCodes()->create(['position_id' => $position->id, 'job_code' => '1001-10']);

    $workOrder = WorkOrder::factory()->create([
        'property_id' => $property->id, 'position_id' => $position->id,
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500,
    ]);

    $monday = Carbon::now($property->timezone)->startOfWeek(Carbon::MONDAY);
    $period = PayrollPeriod::factory()->create([
        'property_id' => $property->id,
        'week_start' => $monday->toDateString(),
        'week_end' => $monday->copy()->addDays(6)->toDateString(),
        'status' => PayrollPeriodStatus::Open,
    ]);
    $timesheet = Timesheet::factory()->create([
        'property_id' => $property->id, 'payroll_period_id' => $period->id, 'status' => TimesheetStatus::Approved,
    ]);

    app(CreateManualTimeEntry::class)->handle($workOrder, [
        'date' => $monday->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00', 'entry_type' => 'work',
    ], null);

    $invoice = app(GenerateInvoice::class)->handle($timesheet->fresh());

    expect($invoice->items->first()->job_code)->toBe('1001-10');

    // Changing the property's code later never rewrites the frozen invoice.
    $property->positionCodes()->where('position_id', $position->id)->update(['job_code' => '9999-99']);
    expect($invoice->fresh()->items->first()->job_code)->toBe('1001-10');

    // The invoice page rolls items up per position with the frozen code.
    $this->actingAs(person('office_manager'))
        ->get(main("/admin/invoices/{$invoice->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('invoice.items.0.job_code', '1001-10')
            ->where('invoice.position_summary.0.position', 'Housekeeper')
            ->where('invoice.position_summary.0.job_code', '1001-10')
            ->where('invoice.position_summary.0.regular_minutes', 480)
            ->where('invoice.position_summary.0.total_bill', 24000));
});
