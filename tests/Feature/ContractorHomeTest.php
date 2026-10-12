<?php

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->contractor = person('contractor');
});

/** A job for the contractor with a week of hours `$weeksAgo` back, and that week's timesheet. */
function contractorJob(Person $contractor, string $position, int $weeksAgo, int $days, TimesheetStatus $status, ?Property $property = null, string $from = '08:00', string $to = '17:00'): WorkOrder
{
    $property ??= Property::factory()->create(['timezone' => 'America/Chicago']);
    $wo = WorkOrder::factory()->create([
        'person_id' => $contractor->id,
        'property_id' => $property->id,
        'position_id' => (Position::query()->where('name', $position)->first() ?? Position::factory()->create(['name' => $position]))->id,
        'start_date' => now()->subMonths(3)->toDateString(),
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

    return $wo;
}

it('shows a contractor their own pay by week, per job, and which weeks can still change', function () {
    contractorJob($this->contractor, 'Houseman', 1, 5, TimesheetStatus::Draft);
    contractorJob($this->contractor, 'Houseman', 2, 5, TimesheetStatus::Invoiced);

    $this->actingAs($this->contractor)
        ->get(qcminute('/'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('minute/dashboard/contractor')
            ->has('jobs', 2)
            ->where('jobs.0.pay_rate', 1800)
            ->has('pay_weeks', 2)
            // 45 h: 40 × $18 + 5 × $27
            ->where('pay_weeks.0.gross', 85500)
            ->where('pay_weeks.0.may_change', true)
            ->where('pay_weeks.0.jobs.0.lines.1.label', 'Overtime')
            ->where('pay_weeks.1.may_change', false));
});

it('keeps each job its own, with its own 40 hours before overtime', function () {
    $property = Property::factory()->create(['timezone' => 'America/Chicago']);
    // Housekeeping by day, bartending in the evening, at the same hotel.
    contractorJob($this->contractor, 'Housekeeper', 1, 4, TimesheetStatus::Invoiced, $property);
    contractorJob($this->contractor, 'Bartender', 1, 3, TimesheetStatus::Invoiced, $property, '18:00', '23:00');

    $this->actingAs($this->contractor)
        ->get(qcminute('/'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('pay_weeks', 1)
            ->has('pay_weeks.0.jobs', 2)
            // 36 h + 15 h: a 51 h week, but neither job reaches 40, so no overtime.
            ->where('pay_weeks.0.minutes', 51 * 60)
            ->where('pay_weeks.0.gross', 51 * 1800));
});

it('never shows a contractor the hotel\'s bill rate, or anyone else\'s pay', function () {
    contractorJob($this->contractor, 'Houseman', 1, 5, TimesheetStatus::Draft);
    contractorJob(person('contractor'), 'Cook', 1, 5, TimesheetStatus::Draft);

    $response = $this->actingAs($this->contractor)->get(qcminute('/'));
    $props = json_encode($response->viewData('page')['props']);

    expect($props)->not->toContain('bill_rate')
        ->and($props)->not->toContain('Cook');
    $response->assertInertia(fn (AssertableInertia $page) => $page->has('jobs', 1)->has('pay_weeks', 1));
});

it('keeps property managers on their own dashboard', function () {
    $this->actingAs(person('property_manager'))
        ->get(qcminute('/'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('minute/dashboard/property-manager'));
});
