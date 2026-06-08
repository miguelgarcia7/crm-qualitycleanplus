<?php

namespace Database\Seeders;

use App\Domain\Adjustments\Actions\CreateManualAdjustment;
use App\Domain\Inventory\Actions\CreateItem;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Jobs\ApplyScheduledContractorCharges;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Item;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Workflows\Actions\CompleteStep;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Enums\MoreStaffUrgency;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * Local/dev demo data: one property with Bible rates, a recruiter assigned to it,
 * and a few contractors on active work orders — enough to exercise the Phase 03
 * pipeline. Idempotent. Never run in production.
 */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::firstOrCreate(
            ['name' => 'Sample Marriott Downtown'],
            [
                'pm_name' => 'Pat Manager',
                'pm_phone' => '602-555-0100',
                'city' => 'Phoenix',
                'state' => 'AZ',
                'timezone' => 'America/Phoenix',
                'tax_rate' => 0.0875,
                'status' => PropertyStatus::Active,
            ],
        );

        if ($property->workOrders()->exists()) {
            return; // already seeded
        }

        // Recruiter who owns this property.
        $recruiter = Person::firstOrCreate(
            ['email' => 'recruiter@example.com'],
            ['name' => 'Rita Recruiter', 'password' => Hash::make('password'), 'status' => PersonStatus::StaffActive, 'email_verified_at' => now()],
        );
        $recruiter->syncRoles('recruiter');
        $property->assignments()->firstOrCreate(
            ['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value],
        );

        // A property manager who logs into QC Minute to approve timesheets.
        $pm = Person::firstOrCreate(
            ['email' => 'pm@example.com'],
            ['name' => 'Paula PM', 'password' => Hash::make('password'), 'status' => PersonStatus::StaffActive, 'email_verified_at' => now()],
        );
        $pm->syncRoles('property_manager');
        $property->assignments()->firstOrCreate(
            ['person_id' => $pm->id, 'role' => PropertyAssignmentRole::PropertyManager->value],
        );

        // A front-desk user who fulfills supply requests (Phase 04 My Tasks queue).
        $frontDesk = Person::firstOrCreate(
            ['email' => 'front-desk@example.com'],
            ['name' => 'Fred Front Desk', 'password' => Hash::make('password'), 'status' => PersonStatus::StaffActive, 'email_verified_at' => now()],
        );
        $frontDesk->syncRoles('front_desk');

        // Two positions with Bible rates (cents).
        $positions = Position::query()->whereIn('slug', ['housekeeper', 'banquet-server'])->get();
        foreach ($positions as $i => $position) {
            $pay = 1800 + $i * 200;
            $bill = $pay + 1200;
            $property->positionRates()->firstOrCreate(
                ['position_id' => $position->id, 'effective_date' => now()->subMonths(3)->toDateString()],
                [
                    'pay_rate' => $pay, 'bill_rate' => $bill,
                    'ot_pay_rate' => (int) round($pay * 1.5), 'ot_bill_rate' => (int) round($bill * 1.5),
                    'is_active' => true, 'created_by' => $recruiter->id,
                ],
            );
        }

        // Three contractors, each on an active work order at the property.
        $workOrders = [];
        $contractors = [];
        foreach (['Carlos Contractor', 'Dana Cleaner', 'Sam Server'] as $i => $name) {
            $position = $positions[$i % $positions->count()];
            $rate = $property->currentRateFor($position->id);

            $contractor = Person::factory()->create([
                'name' => $name,
                'status' => PersonStatus::ContractorActive,
                'primary_recruiter_id' => $recruiter->id,
            ]);
            $contractor->syncRoles('contractor');
            $contractors[] = $contractor;

            $workOrders[] = WorkOrder::create([
                'person_id' => $contractor->id,
                'property_id' => $property->id,
                'position_id' => $position->id,
                'pay_rate' => $rate->pay_rate,
                'bill_rate' => $rate->bill_rate,
                'ot_pay_rate' => $rate->ot_pay_rate,
                'ot_bill_rate' => $rate->ot_bill_rate,
                'start_date' => now()->subMonth()->toDateString(),
                'status' => WorkOrderStatus::Active,
                'source' => WorkOrderSource::RecruiterCreated,
                'created_by' => $recruiter->id,
            ]);
        }

        // Materialize payroll periods, then seed last week's hours (Mon–Fri 9h →
        // 45h = 40 regular + 5 OT) via the real entry path so summaries compute.
        Artisan::call('payroll:ensure-periods');
        $lastMonday = Carbon::now($property->timezone)->startOfWeek(Carbon::MONDAY)->subWeek();
        $createEntry = app(CreateManualTimeEntry::class);

        foreach ($workOrders as $workOrder) {
            for ($day = 0; $day < 5; $day++) {
                $createEntry->handle($workOrder, [
                    'date' => $lastMonday->copy()->addDays($day)->toDateString(),
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                    'entry_type' => 'work',
                ], $recruiter);
            }
        }

        $this->seedInventory($recruiter);
        $this->seedRequestsAndCharges($property, $recruiter, $frontDesk, $contractors, $workOrders);

        // A pending PM-initiated pay increase awaiting recruiter approval (Phase 04b).
        app(StartWorkflow::class)->handle(WorkflowType::PayIncrease, $workOrders[0]->person, $pm, [
            'work_order_id' => $workOrders[0]->id,
            'source' => 'pm',
            'pm_requested_increase_cents' => 100,
            'reason' => 'Consistently strong performance',
        ]);

        // An in-progress termination awaiting front-desk equipment recovery (Phase 04b-ii).
        // Uses the last contractor so it doesn't cancel the pay-increase seeded above.
        app(StartWorkflow::class)->handle(WorkflowType::Termination, $contractors[2], $recruiter, [
            'effective_date' => now()->toDateString(),
            'termination_type' => 'voluntary',
            'reason_category' => 'resignation',
            'notes' => 'Relocating out of state.',
            'rehireable' => true,
        ]);

        // An open more-staff request from the PM awaiting recruiter fulfillment (Phase 04b-iii).
        $moreStaff = MoreStaffRequest::create([
            'property_id' => $property->id,
            'position_id' => $positions->first()->id,
            'quantity_requested' => 2,
            'by_date' => now()->addWeeks(2)->toDateString(),
            'urgency' => MoreStaffUrgency::High,
            'reason' => 'Banquet season ramp-up — need extra coverage.',
            'status' => MoreStaffStatus::Submitted,
            'initiated_by' => $pm->id,
            'assigned_recruiter_id' => $recruiter->id,
        ]);
        $moreStaffWorkflow = app(StartWorkflow::class)->handle(WorkflowType::MoreStaff, $moreStaff, $pm);
        $moreStaff->update(['workflow_id' => $moreStaffWorkflow->id]);

        // A pending personal-info change request awaiting HR verification (Phase 04b-iii).
        app(StartWorkflow::class)->handle(WorkflowType::ChangePersonalInfo, $contractors[1], $contractors[1], [
            'changes' => ['phone' => '(602) 555-0148'],
            'reason' => 'New cell number.',
            'requested_by' => $contractors[1]->id,
        ]);
    }

    /** A uniform (with size variants) and an equipment item, both stocked. */
    private function seedInventory(Person $actor): void
    {
        $createItem = app(CreateItem::class);
        $receive = app(ReceiveStock::class);

        $uniforms = Category::query()->where('slug', 'uniforms')->first();
        if ($uniforms !== null && ! Item::query()->where('name', 'Housekeeping Polo')->exists()) {
            $polo = $createItem->handle([
                'name' => 'Housekeeping Polo',
                'category_id' => $uniforms->id,
                'description' => 'Branded polo shirt',
                'has_variants' => true,
                'variants' => [
                    ['size' => 'S', 'color' => 'Navy', 'reorder_threshold' => 5],
                    ['size' => 'M', 'color' => 'Navy', 'reorder_threshold' => 5],
                    ['size' => 'L', 'color' => 'Navy', 'reorder_threshold' => 5],
                ],
            ], $actor);
            foreach ($polo->variants as $variant) {
                $receive->handle($variant, 20, 'Initial stock', $actor);
            }
        }

        $equipment = Category::query()->where('slug', 'equipment')->first();
        if ($equipment !== null && ! Item::query()->where('name', 'Backpack Vacuum')->exists()) {
            $vacuum = $createItem->handle([
                'name' => 'Backpack Vacuum',
                'category_id' => $equipment->id,
                'description' => 'Commercial backpack vacuum',
                'reorder_threshold' => 2,
            ], $actor);
            foreach ($vacuum->variants as $variant) {
                $receive->handle($variant, 6, 'Initial stock', $actor);
            }
        }

        $office = Category::query()->where('slug', 'office_supplies')->first();
        if ($office !== null && ! Item::query()->where('name', 'Printer Paper')->exists()) {
            $paper = $createItem->handle([
                'name' => 'Printer Paper',
                'category_id' => $office->id,
                'description' => 'Case of 5,000 sheets',
                'reorder_threshold' => 10,
            ], $actor);
            foreach ($paper->variants as $variant) {
                $receive->handle($variant, 4, 'Initial stock', $actor); // low: 4 ≤ 10 threshold
            }
        }
    }

    /**
     * Phase 04 demo data: supply requests in several states so the My Tasks
     * inbox, request queues, charge schedules, payroll deductions and equipment
     * assignments all have something to show.
     *
     * @param  array<int, Person>  $contractors
     * @param  array<int, WorkOrder>  $workOrders
     */
    private function seedRequestsAndCharges(Property $property, Person $recruiter, Person $frontDesk, array $contractors, array $workOrders): void
    {
        if (SupplyRequest::query()->exists() || $contractors === []) {
            return;
        }

        $start = app(StartWorkflow::class);
        $complete = app(CompleteStep::class);

        $uniforms = Category::query()->where('slug', 'uniforms')->firstOrFail();
        $equipment = Category::query()->where('slug', 'equipment')->firstOrFail();
        $office = Category::query()->where('slug', 'office_supplies')->firstOrFail();

        $polo = ItemVariant::query()->whereHas('item', fn ($q) => $q->where('name', 'Housekeeping Polo'))->first();
        $vacuum = ItemVariant::query()->whereHas('item', fn ($q) => $q->where('name', 'Backpack Vacuum'))->first();
        $paper = ItemVariant::query()->whereHas('item', fn ($q) => $q->where('name', 'Printer Paper'))->first();

        // A — uniform charge, fulfilled + applied → shows payroll deductions.
        if ($polo !== null) {
            $this->fulfilledRequest($start, $complete, $recruiter, $frontDesk, [
                'category_id' => $uniforms->id, 'item_variant_id' => $polo->id,
                'beneficiary_type' => 'contractor', 'beneficiary_person_id' => $contractors[0]->id,
                'quantity' => 1, 'charge_amount' => 4500, 'split_payments' => 3,
                'requested_by' => $recruiter->id, 'status' => 'pending',
            ]);
            (new ApplyScheduledContractorCharges)->handle();
        }

        // B — uniform charge, fulfilled but NOT applied → shows outstanding balance.
        if ($polo !== null && isset($contractors[1])) {
            $this->fulfilledRequest($start, $complete, $recruiter, $frontDesk, [
                'category_id' => $uniforms->id, 'item_variant_id' => $polo->id,
                'beneficiary_type' => 'contractor', 'beneficiary_person_id' => $contractors[1]->id,
                'quantity' => 1, 'charge_amount' => 3000, 'split_payments' => 2,
                'requested_by' => $recruiter->id, 'status' => 'pending',
            ]);
        }

        // C — existing office supply, PENDING fulfillment → Front Desk My Tasks + queue.
        if ($paper !== null) {
            $this->startedRequest($start, [
                'category_id' => $office->id, 'item_variant_id' => $paper->id,
                'beneficiary_type' => 'general_office', 'quantity' => 2,
                'purpose' => 'Front office printer', 'requested_by' => $recruiter->id, 'status' => 'pending',
            ], $recruiter);
        }

        // D — new item, PENDING admin approval → Approvals queue.
        $this->startedRequest($start, [
            'category_id' => $equipment->id, 'item_variant_id' => null,
            'beneficiary_type' => 'self', 'quantity' => 1,
            'proposed_item_name' => 'Cordless Drill', 'proposed_description' => '18V drill for maintenance',
            'estimated_cost' => 8900, 'requested_by' => $recruiter->id, 'status' => 'pending',
        ], $recruiter);

        // E — equipment, fulfilled → creates an equipment assignment.
        if ($vacuum !== null) {
            $this->fulfilledRequest($start, $complete, $recruiter, $frontDesk, [
                'category_id' => $equipment->id, 'item_variant_id' => $vacuum->id,
                'beneficiary_type' => 'contractor', 'beneficiary_person_id' => $contractors[0]->id,
                'quantity' => 1, 'requested_by' => $recruiter->id, 'status' => 'pending',
            ]);
        }

        // A manual incentive on the current open period → shows on the grid.
        $openPeriod = PayrollPeriod::query()
            ->where('property_id', $property->id)
            ->where('status', 'open')
            ->orderBy('week_start')
            ->first();
        if ($openPeriod !== null) {
            app(CreateManualAdjustment::class)->handle($openPeriod, [
                'person_id' => $contractors[0]->id,
                'work_order_id' => $workOrders[0]->id,
                'value' => 2500, 'type' => 'incentive', 'is_billable' => false,
                'notes' => 'Perfect attendance bonus',
            ], $recruiter);
        }
    }

    /** Create a supply request and start its workflow (leaves it pending). */
    private function startedRequest(StartWorkflow $start, array $attributes, Person $initiator): SupplyRequest
    {
        $request = SupplyRequest::create($attributes);
        $workflow = $start->handle(WorkflowType::SupplyRequest, $request, $initiator);
        $request->update(['workflow_id' => $workflow->id]);

        return $request;
    }

    /** Create, start, and fulfill a supply request (front desk completes the step). */
    private function fulfilledRequest(StartWorkflow $start, CompleteStep $complete, Person $initiator, Person $frontDesk, array $attributes): void
    {
        $request = $this->startedRequest($start, $attributes, $initiator);
        $step = $request->workflow?->currentStep();
        if ($step !== null) {
            $complete->handle($step, $frontDesk);
        }
    }
}
