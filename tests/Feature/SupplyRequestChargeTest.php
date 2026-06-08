<?php

use App\Domain\Adjustments\Enums\AdjustmentSourceType;
use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Billing\Actions\GenerateInvoice;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Inventory\Actions\CreateItem;
use App\Domain\Inventory\Actions\FulfillSupplyRequest;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Enums\ChargeScheduleStatus;
use App\Domain\Inventory\Jobs\ApplyScheduledContractorCharges;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\ContractorChargeSchedule;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(InventorySeeder::class);
});

/**
 * Property + contractor on an active WO + two open payroll periods + a stocked
 * uniform variant.
 *
 * @return array{property: Property, contractor: Person, workOrder: WorkOrder, variant: ItemVariant, periods: array<int, PayrollPeriod>}
 */
function chargeScenario(): array
{
    $property = Property::factory()->create(['timezone' => 'America/Phoenix', 'tax_rate' => 0.0875]);
    $position = Position::factory()->create();
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);

    $workOrder = WorkOrder::factory()->create([
        'property_id' => $property->id,
        'position_id' => $position->id,
        'person_id' => $contractor->id,
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500,
        'status' => WorkOrderStatus::Active,
    ]);

    $monday = Carbon::now('America/Phoenix')->startOfWeek(Carbon::MONDAY);
    $periods = [];
    foreach ([0, 1] as $w) {
        $start = $monday->copy()->addWeeks($w);
        $periods[] = PayrollPeriod::factory()->create([
            'property_id' => $property->id,
            'week_start' => $start->toDateString(),
            'week_end' => $start->copy()->addDays(6)->toDateString(),
            'status' => PayrollPeriodStatus::Open,
        ]);
    }

    $uniforms = Category::query()->where('slug', 'uniforms')->firstOrFail();
    $polo = app(CreateItem::class)->handle([
        'name' => 'Polo', 'category_id' => $uniforms->id, 'has_variants' => true,
        'variants' => [['size' => 'M', 'color' => 'Navy', 'reorder_threshold' => 5]],
    ], null);
    $variant = $polo->variants->first();
    app(ReceiveStock::class)->handle($variant, 50, 'init', null);

    return compact('property', 'contractor', 'workOrder', 'variant', 'periods');
}

function uniformRequest(array $s, int $chargeCents, ?int $split): SupplyRequest
{
    return SupplyRequest::create([
        'category_id' => Category::query()->where('slug', 'uniforms')->value('id'),
        'item_variant_id' => $s['variant']->id,
        'beneficiary_type' => 'contractor',
        'beneficiary_person_id' => $s['contractor']->id,
        'quantity' => 1,
        'charge_amount' => $chargeCents,
        'split_payments' => $split,
        'requested_by' => $s['contractor']->id,
        'status' => 'pending',
    ]);
}

it('fulfilling a uniform request issues stock and builds a split charge schedule', function () {
    $s = chargeScenario();
    $request = uniformRequest($s, 4000, 2);

    app(FulfillSupplyRequest::class)->handle($request, null);

    expect($request->fresh()->status->value)->toBe('fulfilled')
        ->and($s['variant']->fresh()->current_stock)->toBe(49);

    $schedule = ContractorChargeSchedule::query()->where('person_id', $s['contractor']->id)->firstOrFail();
    expect($schedule->entries()->count())->toBe(2)
        ->and((int) $schedule->entries()->sum('amount'))->toBe(4000)
        ->and($s['contractor']->outstandingChargeBalance())->toBe(4000);
});

it('creates an equipment assignment when an equipment request is fulfilled', function () {
    $s = chargeScenario();
    $equipment = Category::query()->where('slug', 'equipment')->firstOrFail();
    $vac = app(CreateItem::class)->handle(['name' => 'Vacuum', 'category_id' => $equipment->id], null);
    $variant = $vac->variants->first();
    app(ReceiveStock::class)->handle($variant, 5, 'init', null);

    $request = SupplyRequest::create([
        'category_id' => $equipment->id,
        'item_variant_id' => $variant->id,
        'beneficiary_type' => 'contractor',
        'beneficiary_person_id' => $s['contractor']->id,
        'quantity' => 1,
        'requested_by' => $s['contractor']->id,
        'status' => 'pending',
    ]);

    app(FulfillSupplyRequest::class)->handle($request, null);

    expect($s['contractor']->fresh()->load('chargeSchedules'))->and(ContractorChargeSchedule::count())->toBe(0);
    $this->assertDatabaseHas('equipment_assignments', [
        'assigned_to_person_id' => $s['contractor']->id,
        'item_variant_id' => $variant->id,
        'status' => 'assigned',
    ]);
});

it('applies scheduled charges as non-billable payroll deductions', function () {
    $s = chargeScenario();
    app(FulfillSupplyRequest::class)->handle(uniformRequest($s, 4000, 2), null);

    (new ApplyScheduledContractorCharges)->handle();

    $adjustments = TimeEntryAdjustment::query()->where('person_id', $s['contractor']->id)->get();

    expect($adjustments)->toHaveCount(2)
        ->and((int) $adjustments->sum('value'))->toBe(4000)
        ->and($adjustments->every(fn (TimeEntryAdjustment $a): bool => $a->type === AdjustmentType::Deduction && ! $a->is_billable))->toBeTrue()
        ->and($adjustments->every(fn (TimeEntryAdjustment $a): bool => $a->source_type === AdjustmentSourceType::SupplyRequest))->toBeTrue()
        ->and($s['contractor']->outstandingChargeBalance())->toBe(0);

    $schedule = ContractorChargeSchedule::query()->where('person_id', $s['contractor']->id)->firstOrFail();
    expect($schedule->fresh()->status)->toBe(ChargeScheduleStatus::Completed);
});

it('is idempotent: re-running the charge job does not double-apply', function () {
    $s = chargeScenario();
    app(FulfillSupplyRequest::class)->handle(uniformRequest($s, 4000, 2), null);

    (new ApplyScheduledContractorCharges)->handle();
    (new ApplyScheduledContractorCharges)->handle();

    expect(TimeEntryAdjustment::query()->where('person_id', $s['contractor']->id)->count())->toBe(2);
});

it('folds billable incentives into the invoice but never deductions', function () {
    $s = chargeScenario();
    $period = $s['periods'][0];

    // 40h of regular work → 40 × $30 bill = $120,000.
    $createEntry = app(CreateManualTimeEntry::class);
    for ($day = 0; $day < 5; $day++) {
        $createEntry->handle($s['workOrder'], [
            'date' => Carbon::parse($period->week_start)->addDays($day)->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00', 'entry_type' => 'work',
        ], null);
    }

    // A billable incentive (adds to invoice) and a deduction (payroll-only).
    TimeEntryAdjustment::create([
        'person_id' => $s['contractor']->id, 'property_id' => $s['property']->id, 'payroll_period_id' => $period->id,
        'source_type' => AdjustmentSourceType::Manual, 'value' => 5000, 'type' => AdjustmentType::Incentive, 'is_billable' => true,
    ]);
    TimeEntryAdjustment::create([
        'person_id' => $s['contractor']->id, 'property_id' => $s['property']->id, 'payroll_period_id' => $period->id,
        'source_type' => AdjustmentSourceType::Manual, 'value' => 3000, 'type' => AdjustmentType::Deduction, 'is_billable' => false,
    ]);

    $timesheet = Timesheet::factory()->create([
        'property_id' => $s['property']->id, 'payroll_period_id' => $period->id, 'status' => TimesheetStatus::Approved,
    ]);

    $invoice = app(GenerateInvoice::class)->handle($timesheet);

    expect($invoice->work_subtotal)->toBe(120000)
        ->and($invoice->adjustment_total)->toBe(5000)
        ->and($invoice->subtotal)->toBe(125000);
});
