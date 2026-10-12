<?php

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->property = Property::factory()->create(['name' => 'Harbor Inn', 'timezone' => 'America/Chicago']);
    $this->pm = tap(person('property_manager'), fn (Person $p) => $this->property->assignments()->create(['person_id' => $p->id, 'role' => 'property_manager']));
    $this->recruiter = tap(person('recruiter'), fn (Person $p) => $p->update(['name' => 'Rita Reyes']));
    $this->property->assignments()->create(['person_id' => $this->recruiter->id, 'role' => 'recruiter']);
    $this->workOrder = WorkOrder::factory()->create([
        'property_id' => $this->property->id,
        'position_id' => Position::factory()->create()->id,
        'start_date' => now()->subMonths(2)->toDateString(),
        'bill_rate' => 2700, 'ot_bill_rate' => 4050,
    ]);
});

/** A week of 45 hours at the property, `$weeksAgo` back, with its timesheet. */
function dashboardWeek(Property $property, WorkOrder $workOrder, int $weeksAgo, TimesheetStatus $status): Timesheet
{
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

    $timesheet->update(['status' => $status]);

    return $timesheet;
}

it('shows the week waiting on the manager, and what comes next', function () {
    $pending = dashboardWeek($this->property, $this->workOrder, 2, TimesheetStatus::PendingApproval);
    $pending->update(['sent_for_approval_at' => now()->subDays(3), 'sent_for_approval_by' => $this->recruiter->id]);
    dashboardWeek($this->property, $this->workOrder, 1, TimesheetStatus::Draft);

    $this->actingAs($this->pm)
        ->get(qcminute('/'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('minute/dashboard/property-manager')
            ->where('scope', 'Harbor Inn')
            ->has('waiting', 1)
            ->where('waiting.0.id', $pending->id)
            ->where('waiting.0.sent_by', 'Rita Reyes')
            ->where('waiting.0.waiting', '3 days')
            ->where('waiting.0.total_minutes', 45 * 60)
            // 40 × $27 + 5 × $40.50
            ->where('waiting.0.amount', 128250)
            ->has('next_up', 1)
            ->where('next_up.0.recruiter', 'Rita Reyes')
            ->where('next_up.0.declined', false)
            ->has('hours', 8)
            ->where('hours.7.current', true)
            ->where('hours.5.overtime_minutes', 5 * 60));
});

it('totals what the manager owes and lists their open requests', function () {
    Invoice::factory()->create(['property_id' => $this->property->id, 'status' => InvoiceStatus::InvoiceSent, 'total' => 300_00, 'due_date' => now()->addDays(9)]);
    Invoice::factory()->create(['property_id' => $this->property->id, 'status' => InvoiceStatus::InvoiceSent, 'total' => 500_00, 'due_date' => now()->subDays(2)]);
    Invoice::factory()->create(['property_id' => $this->property->id, 'status' => InvoiceStatus::InvoiceSent, 'total' => 900_00, 'due_date' => now()->subDays(20), 'paid_at' => now()->subDays(25)]);
    Invoice::factory()->create(['total' => 700_00, 'due_date' => now()->subDays(2)]); // someone else's
    MoreStaffRequest::factory()->create(['property_id' => $this->property->id, 'status' => MoreStaffStatus::Submitted, 'quantity_requested' => 2, 'quantity_fulfilled' => 1]);
    MoreStaffRequest::factory()->create(['property_id' => $this->property->id, 'status' => MoreStaffStatus::Fulfilled]);

    $this->actingAs($this->pm)
        ->get(qcminute('/'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('invoices.open_total', 800_00)
            ->where('invoices.open_count', 2)
            ->where('invoices.overdue_total', 500_00)
            ->where('invoices.rows.0.days_overdue', 2)
            ->has('requests', 1)
            ->where('requests.0.progress', '1 of 2 placed'));
});

it('never sends the manager pay or pay rates', function () {
    dashboardWeek($this->property, $this->workOrder, 1, TimesheetStatus::PendingApproval);

    $props = json_encode($this->actingAs($this->pm)->get(qcminute('/'))->viewData('page')['props']);

    expect($props)->not->toContain('pay_rate')
        ->and($props)->not->toContain('total_pay');
});
