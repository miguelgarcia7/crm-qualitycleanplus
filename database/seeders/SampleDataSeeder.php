<?php

namespace Database\Seeders;

use App\Domain\Adjustments\Actions\CreateManualAdjustment;
use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Models\AdjustmentItem;
use App\Domain\Billing\Actions\ApproveTimesheet;
use App\Domain\Billing\Actions\SendInvoice;
use App\Domain\Billing\Actions\SubmitTimesheetForApproval;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Devices\Models\Device;
use App\Domain\FieldVisits\Enums\FieldVisitStatus;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\Imports\Actions\CommitImport;
use App\Domain\Imports\Actions\CreateImportBatch;
use App\Domain\Inventory\Actions\CreateItem;
use App\Domain\Inventory\Actions\CreatePurchaseOrder;
use App\Domain\Inventory\Actions\ReceivePurchaseOrder;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Jobs\ApplyScheduledContractorCharges;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Item;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\KnowledgeBase\Actions\UpdateKbArticle;
use App\Domain\KnowledgeBase\Enums\KbArticleStatus;
use App\Domain\KnowledgeBase\Models\KbArticle;
use App\Domain\KnowledgeBase\Models\KbCategory;
use App\Domain\KnowledgeBase\Models\KbTag;
use App\Domain\Marketing\Models\ContactInquiry;
use App\Domain\People\Enums\BackgroundCheckStatus;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Models\PersonExternalId;
use App\Domain\PropertyBible\Enums\ContractType;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Enums\PropertyTimeSource;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Department;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyDepartment;
use App\Domain\Pto\Actions\ApprovePtoRequest;
use App\Domain\Pto\Actions\EnsurePtoYear;
use App\Domain\Pto\Actions\RejectPtoRequest;
use App\Domain\Pto\Actions\SubmitPtoRequest;
use App\Domain\Recruiting\Actions\SubmitApplication;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Enums\JobPostingStatus;
use App\Domain\Recruiting\Models\JobPosting;
use App\Domain\Shared\Enums\FeedbackType;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Enums\TimeEntrySource;
use App\Domain\Time\Enums\TimeEntryType;
use App\Domain\Time\Jobs\RecomputeTimeSummary;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Workflows\Actions\CompleteStep;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Enums\MoreStaffUrgency;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

/**
 * Local/dev demo data: two active properties with Bible rates, contracts and
 * department contacts, contractors on active work orders with several weeks of
 * hours (two of them fully billed into invoices), inventory with purchase
 * orders, workflows of every type in flight, PTO across tenure tiers, the
 * recruiting pipeline end-to-end, and a knowledge base with feedback.
 * Idempotent. Never run in production.
 */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::firstOrCreate(
            ['name' => 'Sample Marriott Downtown'],
            [
                'pm_name' => 'Pat Manager',
                'pm_phone' => '214-555-0100',
                'city' => 'Dallas',
                'state' => 'TX',
                'timezone' => 'America/Chicago',
                'latitude' => 32.7767,   // enables the QR clock-in geofence (Phase 07a)
                'longitude' => -96.7970,
                'tax_rate' => 0.0875,
                'closing_day' => 1, // week ends Monday → Tue–Mon timesheets
                'status' => PropertyStatus::Active,
            ],
        );

        if ($property->workOrders()->exists()) {
            return; // already seeded
        }

        // Recruiter who owns this property.
        $recruiter = Person::firstOrCreate(
            ['email' => 'recruiter@example.com'],
            ['name' => 'Rita Recruiter', 'password' => Hash::make('password'), 'status' => PersonStatus::StaffActive, 'email_verified_at' => now(), 'hire_date' => now()->subMonths(14)->toDateString()],
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
            ['name' => 'Fred Front Desk', 'password' => Hash::make('password'), 'status' => PersonStatus::StaffActive, 'email_verified_at' => now(), 'hire_date' => now()->subMonths(2)->toDateString()],
        );
        $frontDesk->syncRoles('front_desk');

        // Office-staff logins so each role's dashboard is testable (Phase 06).
        // Hire dates spread across the PTO tenure tiers (Phase 08a).
        foreach (['office_manager' => 8, 'payroll' => 3, 'hr' => 26, 'super_admin' => 30] as $role => $monthsEmployed) {
            Person::firstOrCreate(
                ['email' => "{$role}@example.com"],
                ['name' => ucwords(str_replace('_', ' ', $role)), 'password' => Hash::make('password'), 'status' => PersonStatus::StaffActive, 'email_verified_at' => now(), 'hire_date' => now()->subMonths($monthsEmployed)->toDateString()],
            )->syncRoles($role);
        }

        // Three positions with Bible rates (cents).
        $positions = Position::query()->whereIn('slug', ['housekeeper', 'banquet-server', 'cook'])->get();
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

        // Five contractors, each on an active work order at the property.
        $workOrders = [];
        $contractors = [];
        foreach (['Carlos Contractor', 'Dana Cleaner', 'Sam Server', 'Nora Night', 'Owen Overnight'] as $i => $name) {
            $position = $positions[$i % $positions->count()];
            $rate = $property->currentRateFor($position->id);

            $phone = '(214) 555-020'.$i;
            $contractor = Person::factory()->create([
                'name' => $name,
                'status' => PersonStatus::ContractorActive,
                'primary_recruiter_id' => $recruiter->id,
                'phone' => $phone,                                  // QR clock-in lookup (Phase 07a)
                'normalized_phone' => preg_replace('/\D/', '', $phone),
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
                'start_date' => now()->subWeeks(7)->toDateString(),
                'status' => WorkOrderStatus::Active,
                'source' => WorkOrderSource::RecruiterCreated,
                'created_by' => $recruiter->id,
            ]);
        }

        // A second active property so properties, grids and dashboards show more
        // than one row of everything.
        [$plano, $planoWorkOrders] = $this->seedSecondProperty($recruiter, $pm, $positions);

        // Materialize payroll periods, then seed last week's hours (Mon–Fri 9h →
        // 45h = 40 regular + 5 OT). Hours arrive the way they do in production:
        // QR clock events downtown, tablet punches at the Plano kiosk — with ONE
        // manual entry as the missed-punch-correction example.
        Artisan::call('payroll:ensure-periods');

        // Downtown's week ends Monday (closing day) → its weeks run Tue–Mon.
        $mainLastWeekStart = $property->weekStartFor(Carbon::now($property->timezone))->subWeek();

        foreach ($workOrders as $i => $workOrder) {
            for ($day = 0; $day < 5; $day++) {
                $date = $mainLastWeekStart->addDays($day)->toDateString();
                if ($i === 2 && $day === 4) {
                    // Sam forgot to scan on Friday — the recruiter keyed it in.
                    app(CreateManualTimeEntry::class)->handle($workOrder, [
                        'date' => $date, 'start_time' => '09:00', 'end_time' => '18:00', 'entry_type' => 'work',
                    ], $recruiter);
                } else {
                    $this->clockEvent($workOrder, $date, '09:00', '18:00');
                }
            }
        }

        // Plano's week ends Wednesday (closing day) — its timesheets run
        // Thu→Wed, so all its dates anchor on its own week start. Straight 8h
        // days punched on the front-desk tablet kiosk (no OT — rate variety).
        $planoLastWeekStart = $plano->weekStartFor(Carbon::now($plano->timezone))->subWeek();
        foreach ($planoWorkOrders as $workOrder) {
            for ($day = 0; $day < 5; $day++) {
                $this->clockEvent($workOrder, $planoLastWeekStart->addDays($day)->toDateString(), '09:00', '17:00', 'tablet');
            }
        }

        // Billed history (entries → submit → PM approval → frozen invoice):
        // two weeks downtown (the oldest invoice also sent) and one in Plano,
        // so timesheet/invoice lists and the revenue report span properties.
        $this->seedBilledHistory($property, $workOrders, $recruiter, $pm, $mainLastWeekStart);
        $this->seedBilledHistory($plano, $planoWorkOrders, $recruiter, $pm, $planoLastWeekStart, [1], 'tablet', false);
        $oldestInvoice = Invoice::query()->where('property_id', $property->id)->orderBy('id')->first();
        if ($oldestInvoice !== null) {
            app(SendInvoice::class)->handle($oldestInvoice, $recruiter, 'pat.manager@example.com');
        }

        // Hours already on the clock this week so the live grids show activity —
        // each property against its own current week.
        $mainThisWeekStart = $mainLastWeekStart->addWeek();
        $daysSoFar = (int) min(3, $mainThisWeekStart->diffInDays(Carbon::now($property->timezone), false));
        foreach ($workOrders as $workOrder) {
            for ($day = 0; $day < $daysSoFar; $day++) {
                $this->clockEvent($workOrder, $mainThisWeekStart->addDays($day)->toDateString(), '09:00', '17:00');
            }
        }
        $planoThisWeekStart = $planoLastWeekStart->addWeek();
        $planoDaysSoFar = (int) min(3, $planoThisWeekStart->diffInDays(Carbon::now($plano->timezone), false));
        foreach ($planoWorkOrders as $workOrder) {
            for ($day = 0; $day < $planoDaysSoFar; $day++) {
                $this->clockEvent($workOrder, $planoThisWeekStart->addDays($day)->toDateString(), '09:00', '17:00', 'tablet');
            }
        }

        // Two contractors are on the clock RIGHT NOW (open entries — no end yet)
        // so the dashboard's live "On the clock" widget has something to show.
        $this->openClockEntry($workOrders[0], 130);
        $this->openClockEntry($planoWorkOrders[0], 45, 'tablet');

        // Deepen each property to ~6 weeks of populated weekly timesheets so the
        // history list isn't mostly empty. Idempotent: skips weeks that already
        // have entries (billed / last week) and frozen (approved) weeks.
        $this->backfillWeeklyHistory($property, $workOrders, 6);
        $this->backfillWeeklyHistory($plano, $planoWorkOrders, 6);

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
            'changes' => ['phone' => '(214) 555-0148'],
            'reason' => 'New cell number.',
            'requested_by' => $contractors[1]->id,
        ]);

        // A pending transfer to the Plano property awaiting approval (Phase 04b).
        app(StartWorkflow::class)->handle(WorkflowType::Transfer, $contractors[1], $recruiter, [
            'work_order_id' => $workOrders[1]->id,
            'effective_date' => now()->addWeek()->toDateString(),
            'new_property_id' => $plano->id,
            'new_position_id' => $positions->last()->id,
            'new_recruiter_id' => null,
            'pay_rate' => 1950, 'bill_rate' => 3050, 'ot_pay_rate' => 2925, 'ot_bill_rate' => 4575,
            'reason' => 'Closer to home; Plano needs banquet coverage.',
            'notes' => null,
        ]);

        // A pending temporary assignment covering downtown for a week (Phase 04b).
        app(StartWorkflow::class)->handle(WorkflowType::TemporaryAssignment, $planoWorkOrders[0]->person, $recruiter, [
            'home_work_order_id' => $planoWorkOrders[0]->id,
            'new_property_id' => $property->id,
            'position_id' => $positions->first()->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'pay_rate' => 1800, 'bill_rate' => 3000, 'ot_pay_rate' => 2700, 'ot_bill_rate' => 4500,
            'reason' => 'Banquet week coverage downtown.',
        ]);

        // PTO — open allotment + one pending request for the recruiter (Phase 08a).
        app(EnsurePtoYear::class)->handle($recruiter);
        app(SubmitPtoRequest::class)->handle($recruiter, [
            'bucket' => 'vacation',
            'start_date' => now()->addWeeks(3)->toDateString(),
            'end_date' => now()->addWeeks(3)->addDay()->toDateString(),
            'hours' => 16,
            'reason' => 'Family trip',
        ]);

        // PTO across the tiers: hr (tier 3) has an approved request, front desk
        // (tier 1) a rejected one; office manager + payroll get open allotments.
        $superAdmin = Person::query()->where('email', 'super-admin@example.com')->firstOrFail();
        $hr = Person::query()->where('email', 'hr@example.com')->firstOrFail();
        foreach (['office_manager@example.com', 'payroll@example.com'] as $email) {
            app(EnsurePtoYear::class)->handle(Person::query()->where('email', $email)->firstOrFail());
        }
        $hrVacation = app(SubmitPtoRequest::class)->handle($hr, [
            'bucket' => 'vacation',
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addDays(2)->toDateString(),
            'hours' => 24,
            'reason' => 'Cruise',
        ]);
        app(ApprovePtoRequest::class)->handle($hrVacation, $superAdmin);
        $frontDeskDay = app(SubmitPtoRequest::class)->handle($frontDesk, [
            'bucket' => 'scheduled',
            'start_date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'hours' => 8,
            'reason' => 'DMV appointment',
        ]);
        app(RejectPtoRequest::class)->handle($frontDeskDay, $superAdmin, 'Coverage gap that week — please pick another day.');

        // A front-desk tablet for the property, ready to pair (Phase 07c),
        // plus an already-activated kiosk at Plano.
        Device::create([
            'property_id' => $property->id,
            'name' => 'Front Desk Tablet',
            'activation_code' => 'QCP123',
            'created_by' => $recruiter->id,
        ]);
        Device::create([
            'property_id' => $plano->id,
            'name' => 'Lobby Kiosk',
            'activation_code' => 'QCP456',
            'is_activated' => true,
            'app_version' => '1.0.0',
            'last_seen_at' => now()->subHours(2),
            'created_by' => $recruiter->id,
        ]);

        // An import-only property with one committed weekly hour import (Phase 05).
        $this->seedImport($recruiter, $positions->first());

        // Recruiter field visits — one closed, one open (Phase 07b).
        FieldVisit::create([
            'person_id' => $recruiter->id, 'property_id' => $property->id, 'status' => FieldVisitStatus::Closed,
            'check_in_at' => now()->subDay()->setTime(9, 0), 'check_out_at' => now()->subDay()->setTime(10, 15),
            'check_in_gps_lat' => 32.7767, 'check_in_gps_lng' => -96.7970, 'check_in_gps_status' => 'ok',
            'was_inside_geofence' => true, 'check_out_gps_status' => 'ok',
        ]);
        FieldVisit::create([
            'person_id' => $recruiter->id, 'property_id' => $property->id, 'status' => FieldVisitStatus::Open,
            'check_in_at' => now()->subMinutes(20),
            'check_in_gps_lat' => 32.7767, 'check_in_gps_lng' => -96.7970, 'check_in_gps_status' => 'ok',
            'was_inside_geofence' => true,
        ]);
        FieldVisit::create([
            'person_id' => $recruiter->id, 'property_id' => $plano->id, 'status' => FieldVisitStatus::Closed,
            'check_in_at' => now()->subDays(2)->setTime(14, 0), 'check_out_at' => now()->subDays(2)->setTime(15, 30),
            'check_in_gps_lat' => 33.0198, 'check_in_gps_lng' => -96.6989, 'check_in_gps_status' => 'ok',
            'was_inside_geofence' => true, 'check_out_gps_status' => 'ok',
        ]);

        // A past contractor with a closed work order, terminated two months ago.
        $former = Person::factory()->create([
            'name' => 'Tina Past',
            'status' => PersonStatus::Terminated,
            'primary_recruiter_id' => $recruiter->id,
        ]);
        $former->syncRoles('contractor');
        WorkOrder::create([
            'person_id' => $former->id,
            'property_id' => $property->id,
            'position_id' => $positions->first()->id,
            'pay_rate' => 1800, 'bill_rate' => 3000, 'ot_pay_rate' => 2700, 'ot_bill_rate' => 4500,
            'start_date' => now()->subMonths(8)->toDateString(),
            'end_date' => now()->subMonths(2)->toDateString(),
            'status' => WorkOrderStatus::Closed,
            'source' => WorkOrderSource::RecruiterCreated,
            'created_by' => $recruiter->id,
        ]);

        $this->seedContracts($property, $plano);
        $this->seedPropertyDepartments($property, $plano);
        $this->seedPurchaseOrders($frontDesk);

        $this->seedRecruiting($property, $plano, $recruiter);

        $this->seedKnowledgeBase($frontDesk, $contractors);

        // A third active property (Omni Hotel) owned solely by a SECOND recruiter,
        // Ruben — its own contractors with ~6 weeks of clocked history + this week.
        $this->seedOmniProperty($positions);
    }

    /**
     * The second active property (Plano): PM + recruiter assignments, Bible
     * rates for both demo positions, and two contractors on active work orders.
     *
     * @param  Collection<int, Position>  $positions
     * @return array{Property, array<int, WorkOrder>}
     */
    private function seedSecondProperty(Person $recruiter, Person $pm, $positions): array
    {
        $property = Property::create([
            'name' => 'Sample Hilton Plano',
            'pm_name' => 'Paula PM',
            'pm_phone' => '469-555-0190',
            'city' => 'Plano',
            'state' => 'TX',
            'timezone' => 'America/Chicago',
            'latitude' => 33.0198,
            'longitude' => -96.6989,
            'tax_rate' => 0.081,
            'closing_day' => 3, // week ends Wednesday → Thu–Wed timesheets
            'status' => PropertyStatus::Active,
        ]);
        $property->assignments()->create(['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value]);
        $property->assignments()->create(['person_id' => $pm->id, 'role' => PropertyAssignmentRole::PropertyManager->value]);

        foreach ($positions as $i => $position) {
            $pay = 1700 + $i * 250;
            $bill = $pay + 1100;
            $property->positionRates()->create([
                'position_id' => $position->id, 'effective_date' => now()->subMonths(2)->toDateString(),
                'pay_rate' => $pay, 'bill_rate' => $bill,
                'ot_pay_rate' => (int) round($pay * 1.5), 'ot_bill_rate' => (int) round($bill * 1.5),
                'is_active' => true, 'created_by' => $recruiter->id,
            ]);
        }

        $workOrders = [];
        foreach (['Eddie Evenings', 'Fiona Floater'] as $i => $name) {
            $position = $positions[$i % $positions->count()];
            $rate = $property->currentRateFor($position->id);

            $phone = '(469) 555-021'.$i;
            $contractor = Person::factory()->create([
                'name' => $name,
                'status' => PersonStatus::ContractorActive,
                'primary_recruiter_id' => $recruiter->id,
                'phone' => $phone,
                'normalized_phone' => preg_replace('/\D/', '', $phone),
            ]);
            $contractor->syncRoles('contractor');

            $workOrders[] = WorkOrder::create([
                'person_id' => $contractor->id,
                'property_id' => $property->id,
                'position_id' => $position->id,
                'pay_rate' => $rate->pay_rate,
                'bill_rate' => $rate->bill_rate,
                'ot_pay_rate' => $rate->ot_pay_rate,
                'ot_bill_rate' => $rate->ot_bill_rate,
                'start_date' => now()->subWeeks(7)->toDateString(),
                'status' => WorkOrderStatus::Active,
                'source' => WorkOrderSource::RecruiterCreated,
                'created_by' => $recruiter->id,
            ]);
        }

        return [$property, $workOrders];
    }

    /**
     * A third active property (Omni Hotel) for a SECOND recruiter, Ruben, who is
     * the only person assigned to it. Isolated recruiter/property pair with its
     * own contractors and six completed weeks of clocked history plus the
     * current week — so the People "Hours" tab and the live grids have data.
     *
     * @param  Collection<int, Position>  $positions
     */
    private function seedOmniProperty($positions): void
    {
        $omni = Property::create([
            'name' => 'Omni Hotel',
            'pm_name' => 'Olivia Owner',
            'pm_phone' => '817-555-0300',
            'city' => 'Fort Worth',
            'state' => 'TX',
            'timezone' => 'America/Chicago',
            'latitude' => 32.7555,
            'longitude' => -97.3308,
            'tax_rate' => 0.0825,
            'closing_day' => 7, // week ends Sunday → Mon–Sun timesheets
            'status' => PropertyStatus::Active,
        ]);

        // Second recruiter — and the ONLY person assigned to Omni (no PM).
        $ruben = Person::firstOrCreate(
            ['email' => 'recruiter2@example.com'],
            ['name' => 'Ruben Recruiter', 'password' => Hash::make('password'), 'status' => PersonStatus::StaffActive, 'email_verified_at' => now(), 'hire_date' => now()->subMonths(6)->toDateString()],
        );
        $ruben->syncRoles('recruiter');
        $omni->assignments()->create(['person_id' => $ruben->id, 'role' => PropertyAssignmentRole::Recruiter->value]);

        // Bible rates for the two demo positions.
        foreach ($positions as $i => $position) {
            $pay = 1900 + $i * 200;
            $bill = $pay + 1300;
            $omni->positionRates()->create([
                'position_id' => $position->id, 'effective_date' => now()->subMonths(2)->toDateString(),
                'pay_rate' => $pay, 'bill_rate' => $bill,
                'ot_pay_rate' => (int) round($pay * 1.5), 'ot_bill_rate' => (int) round($bill * 1.5),
                'is_active' => true, 'created_by' => $ruben->id,
            ]);
        }

        // Three contractors under Ruben, each on an active WO opened ~5 weeks ago.
        $workOrders = [];
        foreach (['Gloria Glove', 'Hank Hallway', 'Ivy Linens'] as $i => $name) {
            $position = $positions[$i % $positions->count()];
            $rate = $omni->currentRateFor($position->id);

            $phone = '(817) 555-031'.$i;
            $contractor = Person::factory()->create([
                'name' => $name,
                'status' => PersonStatus::ContractorActive,
                'primary_recruiter_id' => $ruben->id,
                'phone' => $phone,
                'normalized_phone' => preg_replace('/\D/', '', $phone),
            ]);
            $contractor->syncRoles('contractor');

            $workOrders[] = WorkOrder::create([
                'person_id' => $contractor->id,
                'property_id' => $omni->id,
                'position_id' => $position->id,
                'pay_rate' => $rate->pay_rate,
                'bill_rate' => $rate->bill_rate,
                'ot_pay_rate' => $rate->ot_pay_rate,
                'ot_bill_rate' => $rate->ot_bill_rate,
                'start_date' => now()->subWeeks(7)->toDateString(),
                'status' => WorkOrderStatus::Active,
                'source' => WorkOrderSource::RecruiterCreated,
                'created_by' => $ruben->id,
            ]);
        }

        // Each week needs its payroll period before clock events can attach
        // (clockEvent looks the period up by date). ensure-periods already ran
        // before Omni existed, so we materialize Omni's periods here.
        $tz = $omni->timezone;
        $thisWeekStart = $omni->weekStartFor(Carbon::now($tz));

        // Six completed weeks of populated weekly timesheets so Omni shows up
        // across the timesheets history with hours (first contractor pulls a
        // little overtime, handled inside backfillWeeklyHistory).
        $this->backfillWeeklyHistory($omni, $workOrders, 6);

        // This week so far — only the weekdays elapsed (capped Mon–Fri).
        $this->ensurePayrollPeriod($omni, $thisWeekStart);
        $daysSoFar = max(1, min(5, (int) $thisWeekStart->diffInDays(Carbon::now($tz)) + 1));
        foreach ($workOrders as $workOrder) {
            for ($day = 0; $day < $daysSoFar; $day++) {
                $this->clockEvent($workOrder, $thisWeekStart->copy()->addDays($day)->toDateString(), '09:00', '17:00');
            }
        }

        // One contractor on the clock right now for the live "On the clock" widget.
        $this->openClockEntry($workOrders[0], 75);

        // Omni was created after the global ensure-periods ran — re-run it so Omni
        // gets the same current + upcoming open periods/timesheets as the others.
        Artisan::call('payroll:ensure-periods');
    }

    /** Materialize a property's weekly payroll period (idempotent). */
    private function ensurePayrollPeriod(Property $property, CarbonInterface $weekStart): PayrollPeriod
    {
        return PayrollPeriod::firstOrCreate(
            ['property_id' => $property->id, 'week_start' => $weekStart->toDateString()],
            ['week_end' => $weekStart->copy()->addDays(6)->toDateString(), 'status' => PayrollPeriodStatus::Open],
        );
    }

    /**
     * Fill the last $weeks completed weeks for a property with Mon–Fri clock
     * events + a draft timesheet, so the timesheets history shows populated
     * weeks. Idempotent and non-destructive: a week whose timesheet is already
     * submitted/approved (frozen) is left untouched, and a (work order, week)
     * that already has entries is skipped — so it overlays cleanly on billed
     * history and the current/last-week seeding without doubling hours.
     *
     * @param  array<int, WorkOrder>  $workOrders
     */
    private function backfillWeeklyHistory(Property $property, array $workOrders, int $weeks): void
    {
        $thisWeekStart = $property->weekStartFor(Carbon::now($property->timezone));

        for ($w = $weeks; $w >= 1; $w--) {
            $weekStart = $thisWeekStart->copy()->subWeeks($w);
            $period = $this->ensurePayrollPeriod($property, $weekStart);
            $timesheet = $period->timesheet()->firstOrCreate(
                [],
                ['property_id' => $property->id, 'source' => 'clock_in', 'status' => TimesheetStatus::Draft],
            );

            // Never add entries to a submitted/approved (frozen) week.
            if ($timesheet->status !== TimesheetStatus::Draft) {
                continue;
            }

            $weekEnd = $weekStart->copy()->addDays(6)->toDateString();
            foreach ($workOrders as $i => $workOrder) {
                // Skip WOs not yet active that week, and weeks already clocked.
                if ($workOrder->start_date->toDateString() > $weekEnd) {
                    continue;
                }
                $alreadyClocked = TimeEntry::query()
                    ->where('work_order_id', $workOrder->id)
                    ->where('payroll_period_id', $period->id)
                    ->exists();
                if ($alreadyClocked) {
                    continue;
                }

                for ($day = 0; $day < 5; $day++) {
                    $this->clockEvent(
                        $workOrder,
                        $weekStart->copy()->addDays($day)->toDateString(),
                        '09:00',
                        $i === 0 ? '18:00' : '17:00',
                    );
                }
            }
        }
    }

    /**
     * Past weeks pushed through the real billing pipeline: clock events →
     * recruiter submits → PM approves (which freezes an invoice) — and the
     * property's oldest invoice is also marked sent.
     *
     * @param  array<int, WorkOrder>  $workOrders
     * @param  array<int, int>  $weeks  weeks back from week -1 (lastMonday)
     */
    private function seedBilledHistory(
        Property $property,
        array $workOrders,
        Person $recruiter,
        Person $pm,
        CarbonInterface $lastWeekStart,
        array $weeks = [2, 1],
        string $method = 'qr',
        bool $firstPullsOvertime = true,
    ): void {
        $submit = app(SubmitTimesheetForApproval::class);
        $approve = app(ApproveTimesheet::class);

        foreach ($weeks as $weeksBefore) { // weeks back from week -1 ($lastWeekStart)
            $weekStart = $lastWeekStart->copy()->subWeeks($weeksBefore);
            $period = PayrollPeriod::create([
                'property_id' => $property->id,
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekStart->copy()->addDays(6)->toDateString(),
                'status' => PayrollPeriodStatus::Open,
            ]);
            $timesheet = $period->timesheet()->create([
                'property_id' => $property->id,
                'source' => 'clock_in',
                'status' => TimesheetStatus::Draft,
            ]);

            foreach ($workOrders as $i => $workOrder) {
                for ($day = 0; $day < 5; $day++) {
                    $this->clockEvent(
                        $workOrder,
                        $weekStart->copy()->addDays($day)->toDateString(),
                        '09:00',
                        $i === 0 && $firstPullsOvertime ? '18:00' : '17:00',
                        $method,
                    );
                }
            }

            $approve->handle($submit->handle($timesheet, $recruiter), $pm);
        }

    }

    /**
     * Property contracts (Phase 02): an active MSA downtown and a Plano SOW that
     * expires within the 30-day alert window, each with a stored document.
     */
    private function seedContracts(Property $downtown, Property $plano): void
    {
        $payroll = Person::query()->where('email', 'payroll@example.com')->firstOrFail();
        $disk = (string) config('filesystems.default');

        $contracts = [
            [$downtown, 'Master Service Agreement 2026', ContractType::Msa, now()->subMonths(5), now()->addMonths(7)],
            [$plano, 'Housekeeping SOW — Summer 2026', ContractType::Sow, now()->subMonth(), now()->addDays(20)],
        ];

        foreach ($contracts as [$property, $name, $type, $effective, $expires]) {
            $contract = Contract::create([
                'property_id' => $property->id,
                'name' => $name,
                'type' => $type,
                'effective_date' => $effective->toDateString(),
                'expiration_date' => $expires->toDateString(),
                'uploaded_by' => $payroll->id,
                'notes' => 'Demo contract (seeded).',
                'is_active' => true,
            ]);

            $path = 'contracts/'.Str::slug($name).'.txt';
            Storage::disk($disk)->put($path, "Placeholder contract document for {$property->name}.");
            $contract->file()->create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => Str::slug($name).'.txt',
                'mime_type' => 'text/plain',
                'size' => Storage::disk($disk)->size($path),
                'uploaded_by' => $payroll->id,
            ]);
        }
    }

    /** Department assignments with on-site manager contacts (Property Bible §2). */
    private function seedPropertyDepartments(Property $downtown, Property $plano): void
    {
        $assignments = [
            [$downtown, 'housekeeping', 'Hank Houser', '214-555-0150'],
            [$downtown, 'banquets', 'Bonnie Banks', '214-555-0151'],
            [$plano, 'housekeeping', 'Helen Hosk', '469-555-0152'],
        ];

        foreach ($assignments as [$property, $slug, $manager, $phone]) {
            $department = Department::query()->where('slug', $slug)->first();
            if ($department !== null) {
                PropertyDepartment::firstOrCreate(
                    ['property_id' => $property->id, 'department_id' => $department->id],
                    ['manager_name' => $manager, 'manager_phone' => $phone, 'is_active' => true],
                );
            }
        }
    }

    /** One received polo restock and one order still with the vendor (ADR-0012). */
    private function seedPurchaseOrders(Person $actor): void
    {
        $create = app(CreatePurchaseOrder::class);

        $polos = ItemVariant::query()->whereHas('item', fn ($q) => $q->where('name', 'Housekeeping Polo'))->get();
        if ($polos->isNotEmpty()) {
            $po = $create->handle([
                'notes' => 'Quarterly polo restock',
                'items' => $polos->map(fn (ItemVariant $v): array => [
                    'item_variant_id' => $v->id, 'quantity' => 10, 'estimated_unit_cost' => 1500,
                ])->all(),
            ], $actor);
            $po->update(['status' => PurchaseOrderStatus::Ordered, 'ordered_at' => now()->subDays(7)]);
            app(ReceivePurchaseOrder::class)->handle($po->refresh(), $actor);
        }

        $vacuum = ItemVariant::query()->whereHas('item', fn ($q) => $q->where('name', 'Backpack Vacuum'))->first();
        if ($vacuum !== null) {
            $po = $create->handle([
                'notes' => 'Two more backpack vacuums for Plano',
                'items' => [['item_variant_id' => $vacuum->id, 'quantity' => 2, 'estimated_unit_cost' => 28900]],
            ], $actor);
            $po->update(['status' => PurchaseOrderStatus::Ordered, 'ordered_at' => now()->subDays(2)]);
        }
    }

    /**
     * Knowledge base (Phase 08c): a category tree, published articles (one
     * contractor-gated, one with version history, one with an attachment), a
     * draft, an archived article, and reader feedback in several states.
     *
     * @param  array<int, Person>  $contractors
     */
    private function seedKnowledgeBase(Person $reader, array $contractors): void
    {
        $hr = Person::query()->where('email', 'hr@example.com')->firstOrFail();
        $officeManager = Person::query()->where('email', 'office_manager@example.com')->firstOrFail();

        $operations = KbCategory::create(['name' => 'Operations', 'slug' => 'operations', 'description' => 'Day-to-day procedures.', 'sort_order' => 1]);
        $timeClock = KbCategory::create(['name' => 'Time Clock', 'slug' => 'time-clock', 'parent_id' => $operations->id, 'sort_order' => 1]);
        $hrPolicies = KbCategory::create(['name' => 'HR Policies', 'slug' => 'hr-policies', 'description' => 'Policies for W-2 staff.', 'sort_order' => 2]);

        $clockIn = KbArticle::create([
            'title' => 'How to clock in with the QR code',
            'slug' => 'how-to-clock-in-with-the-qr-code',
            'summary' => 'Scan the property QR code, take the selfie, and you are on the clock.',
            'content' => '<h2>Steps</h2><ol><li>Open your phone camera and scan the QR code at the entrance.</li><li>Confirm your name.</li><li>Take the selfie when prompted.</li></ol><p>If the page says you are outside the property, move closer to the building and try again.</p>',
            'author_id' => $hr->id,
            'status' => KbArticleStatus::Published,
            'published_at' => now()->subDays(10),
            'is_featured' => true,
        ]);
        $clockIn->categories()->attach([$operations->id, $timeClock->id]);
        $clockIn->tags()->attach(KbTag::findOrCreateByName('Time Clock')->id);
        $contractorRole = Role::findByName('contractor');
        $clockIn->roles()->attach($contractorRole->id);

        $pto = KbArticle::create([
            'title' => 'PTO policy overview',
            'slug' => 'pto-policy-overview',
            'summary' => 'Tiers, buckets, and how to request time off.',
            'content' => '<p>PTO accrues by tenure tier across three buckets: vacation, scheduled, and unscheduled. Submit requests from the Time Off page; hours deduct when you submit.</p>',
            'author_id' => $hr->id,
            'status' => KbArticleStatus::Published,
            'published_at' => now()->subDays(5),
        ]);
        $pto->categories()->attach($hrPolicies->id);
        $pto->tags()->attach(KbTag::findOrCreateByName('Benefits')->id);

        // One edit so the version history has a snapshot.
        app(UpdateKbArticle::class)->handle($pto, [
            'title' => $pto->title,
            'summary' => $pto->summary,
            'content' => $pto->content.'<p>Unused hours are forfeited at your hire anniversary — no rollover.</p>',
            'is_featured' => false,
        ], $hr, 'Added the no-rollover note');

        $draft = KbArticle::create([
            'title' => 'Uniform request flow (in progress)',
            'slug' => 'uniform-request-flow',
            'summary' => 'Draft — how uniform requests will move through inventory.',
            'content' => '<p>Outline only.</p>',
            'author_id' => $hr->id,
        ]);
        $draft->categories()->attach($operations->id);

        $pto->feedback()->create(['person_id' => $reader->id, 'type' => FeedbackType::Helpful, 'url' => '/admin/kb/article/pto-policy-overview']);
        $pto->feedback()->create([
            'person_id' => $reader->id,
            'type' => FeedbackType::Suggestion,
            'message' => 'Could we add an example of a partial-day request?',
            'url' => '/admin/kb/article/pto-policy-overview',
        ]);

        // Billing: how hours become invoices (office-manager authored).
        $billing = KbCategory::create(['name' => 'Billing & Invoices', 'slug' => 'billing-invoices', 'description' => 'How hours become invoices.', 'sort_order' => 3]);
        $invoicing = KbArticle::create([
            'title' => 'How weekly invoices are generated',
            'slug' => 'how-weekly-invoices-are-generated',
            'summary' => 'From approved timesheet to frozen invoice, step by step.',
            'content' => '<p>Each property runs Monday–Sunday payroll periods. When the recruiter submits the week and the property manager approves it, the system freezes an invoice with the rates in effect that week.</p><ul><li>Submitting locks the period.</li><li>Approval generates the invoice.</li><li>Voiding requires a reason and reopens nothing — re-import or adjust next week.</li></ul>',
            'author_id' => $officeManager->id,
            'status' => KbArticleStatus::Published,
            'published_at' => now()->subDays(3),
        ]);
        $invoicing->categories()->attach($billing->id);
        $invoicing->tags()->attach([KbTag::findOrCreateByName('Billing')->id, KbTag::findOrCreateByName('Payroll')->id]);

        // Supplies: published with a stored attachment (sizing chart).
        $supplies = KbArticle::create([
            'title' => 'Requesting supplies and uniforms',
            'slug' => 'requesting-supplies-and-uniforms',
            'summary' => 'Where supply requests go and who fulfills them.',
            'content' => '<ol><li>Open Inventory → Supply Requests and pick the item (or propose a new one).</li><li>Uniform charges can be split across paychecks.</li><li>Front desk fulfills from stock; new items go to admin approval first.</li></ol>',
            'author_id' => $hr->id,
            'status' => KbArticleStatus::Published,
            'published_at' => now()->subDays(7),
        ]);
        $supplies->categories()->attach($operations->id);
        $supplies->tags()->attach([KbTag::findOrCreateByName('Inventory')->id, KbTag::findOrCreateByName('Uniforms')->id]);

        $disk = (string) config('filesystems.default');
        $chartPath = 'kb/polo-sizing-chart.txt';
        Storage::disk($disk)->put($chartPath, "Housekeeping polo sizing chart\nS: chest 34-36\nM: chest 38-40\nL: chest 42-44");
        $supplies->files()->create([
            'disk' => $disk,
            'path' => $chartPath,
            'original_name' => 'polo-sizing-chart.txt',
            'mime_type' => 'text/plain',
            'size' => Storage::disk($disk)->size($chartPath),
            'uploaded_by' => $hr->id,
        ]);

        // An archived article (Archived tab; hidden from readers).
        $superseded = KbArticle::create([
            'title' => '2024 uniform policy (superseded)',
            'slug' => '2024-uniform-policy',
            'summary' => 'Old uniform policy kept for reference.',
            'content' => '<p>Replaced by the supply-request flow — see "Requesting supplies and uniforms".</p>',
            'author_id' => $hr->id,
            'status' => KbArticleStatus::Archived,
            'published_at' => now()->subMonths(6),
        ]);
        $superseded->categories()->attach($operations->id);

        // Contractor votes on the clock-in guide + a resolved issue report.
        $clockIn->feedback()->create(['person_id' => $contractors[0]->id, 'type' => FeedbackType::Helpful, 'url' => '/kb/how-to-clock-in-with-the-qr-code']);
        $clockIn->feedback()->create(['person_id' => $contractors[1]->id, 'type' => FeedbackType::NotHelpful, 'url' => '/kb/how-to-clock-in-with-the-qr-code']);
        $clockIn->feedback()->create([
            'person_id' => $contractors[0]->id,
            'type' => FeedbackType::Issue,
            'message' => 'QR poster missing at the loading dock entrance.',
            'url' => '/kb/how-to-clock-in-with-the-qr-code',
        ])->markResolved($hr, 'Printed and posted a new QR sign.');

        // Some read activity so view counts aren't all zero.
        $clockIn->forceFill(['view_count' => 34])->save();
        $pto->forceFill(['view_count' => 21])->save();
        $invoicing->forceFill(['view_count' => 9])->save();
        $supplies->forceFill(['view_count' => 15])->save();
    }

    /**
     * Marketing recruiting (Phase 08b-i/ii): job postings in every status,
     * applications across the pipeline (submitted, reviewing, rejected, and one
     * promoted to a working contractor — all via the real SubmitApplication path
     * so applicant People are created), and contact leads of both types.
     */
    private function seedRecruiting(Property $property, Property $plano, Person $recruiter): void
    {
        $housekeeperPosting = JobPosting::create([
            'status' => JobPostingStatus::Published,
            'title' => 'Housekeeper',
            'slug' => 'housekeeper-dallas',
            'pay_range' => '$16 - $18 / hr',
            'content' => 'Join our hospitality team cleaning guest rooms at a downtown Dallas hotel. Reliable transportation a plus.',
            'hour_start' => '08:00',
            'hour_end' => '16:00',
            'property_id' => $property->id,
            'created_by' => $recruiter->id,
        ]);

        JobPosting::create([
            'status' => JobPostingStatus::Published,
            'title' => 'Banquet Server',
            'slug' => 'banquet-server-dallas',
            'pay_range' => '$15 - $17 / hr',
            'content' => 'Serve banquets and events; evening and weekend availability required.',
            'hour_start' => '16:00',
            'hour_end' => '23:00',
            'property_id' => $property->id,
            'created_by' => $recruiter->id,
        ]);

        JobPosting::create([
            'status' => JobPostingStatus::Draft,
            'title' => 'Public Area Attendant',
            'slug' => 'public-area-attendant-dallas',
            'pay_range' => '$15 / hr',
            'content' => 'Maintain lobby and public areas. (Draft — not yet published.)',
            'location_label' => 'Dallas, TX',
            'created_by' => $recruiter->id,
        ]);

        $alexApplication = app(SubmitApplication::class)->handle([
            'first_name' => 'Alex', 'last_name' => 'Applicant', 'email' => 'alex.applicant@example.com',
            'phone' => '(214) 555-0301', 'address' => '500 Commerce St', 'city' => 'Dallas',
            'state' => 'TX', 'zip' => '75201', 'position' => 'Housekeeper', 'desired_salary' => '17',
            'start_date' => now()->addWeek()->toDateString(), 'dob' => '1995-03-12',
            'transportation' => '1', 'work_at_qcp' => '0', 'usa_citizen' => '1',
            'another_staff_agency' => '0', 'convicted_felon' => '0',
            'full_name' => 'Jordan Applicant', 'emergency_phone' => '(214) 555-0302',
            'relationship' => 'Sibling', 'acknowledgement' => '1',
        ], $housekeeperPosting);

        // Alex is under active review with a partially-complete checklist (08b-ii):
        // background check ordered, documents still outstanding — shows up in the
        // front-desk "Onboarding docs pending" widget and the Reviewing filter.
        $alexApplication->update([
            'status' => JobApplicationStatus::Reviewing,
            'reviewed_by' => $recruiter->id,
            'reviewed_at' => now()->subDay(),
        ]);
        $alexApplication->person->forceFill(['background_check_status' => BackgroundCheckStatus::Pending])->save();

        app(SubmitApplication::class)->handle([
            'first_name' => 'Taylor', 'last_name' => 'Seeker', 'email' => 'taylor.seeker@example.com',
            'phone' => '(972) 555-0311', 'address' => '1200 Elm St', 'city' => 'Dallas',
            'state' => 'TX', 'zip' => '75202', 'position' => 'Banquet Server', 'desired_salary' => '16',
            'start_date' => now()->addWeeks(2)->toDateString(), 'dob' => '1998-07-22',
            'transportation' => '0', 'work_at_qcp' => '1', 'work_at_qcp_explain' => 'Summer 2024',
            'usa_citizen' => '1', 'another_staff_agency' => '1', 'non_complete' => 'No',
            'convicted_felon' => '0', 'acknowledgement' => '1',
        ]);

        // A posting that ran its course (history on the postings list).
        JobPosting::create([
            'status' => JobPostingStatus::Closed,
            'title' => 'Overnight Cleaner',
            'slug' => 'overnight-cleaner-plano',
            'pay_range' => '$17 / hr',
            'content' => 'Filled — kept for history.',
            'property_id' => $plano->id,
            'created_by' => $recruiter->id,
        ]);

        // A rejected application (Rejected tab + decided history).
        $rejected = app(SubmitApplication::class)->handle([
            'first_name' => 'Riley', 'last_name' => 'Rushed', 'email' => 'riley.rushed@example.com',
            'phone' => '(214) 555-0321', 'address' => '88 Griffin St W', 'city' => 'Dallas',
            'state' => 'TX', 'zip' => '75202', 'position' => 'Housekeeper', 'desired_salary' => '18',
            'start_date' => now()->addDays(3)->toDateString(), 'dob' => '2000-01-30',
            'transportation' => '1', 'work_at_qcp' => '0', 'usa_citizen' => '1',
            'another_staff_agency' => '0', 'convicted_felon' => '0', 'acknowledgement' => '1',
        ], $housekeeperPosting);
        $rejected->update([
            'status' => JobApplicationStatus::Rejected,
            'reviewed_by' => $recruiter->id,
            'reviewed_at' => now()->subDays(2),
            'rejected_reason' => 'No weekend availability.',
        ]);

        // A promoted application: the applicant became a contractor and is now
        // on a fresh work order at Plano (full lifecycle in one record).
        $promoted = app(SubmitApplication::class)->handle([
            'first_name' => 'Pat', 'last_name' => 'Promoted', 'email' => 'pat.promoted@example.com',
            'phone' => '(469) 555-0331', 'address' => '700 Legacy Dr', 'city' => 'Plano',
            'state' => 'TX', 'zip' => '75024', 'position' => 'Banquet Server', 'desired_salary' => '17',
            'start_date' => now()->subDays(2)->toDateString(), 'dob' => '1992-11-05',
            'transportation' => '1', 'work_at_qcp' => '0', 'usa_citizen' => '1',
            'another_staff_agency' => '0', 'convicted_felon' => '0', 'acknowledgement' => '1',
        ]);
        $hired = $promoted->person;
        $hired->forceFill([
            'status' => PersonStatus::ContractorActive,
            'converted_to_contractor_at' => now()->subDays(3),
            'primary_recruiter_id' => $recruiter->id,
        ])->save();
        $hired->syncRoles('contractor');
        $promoted->update([
            'status' => JobApplicationStatus::Promoted,
            'reviewed_by' => $recruiter->id,
            'reviewed_at' => now()->subDays(5),
            'promoted_by' => $recruiter->id,
            'promoted_at' => now()->subDays(3),
        ]);
        $serverPosition = Position::query()->where('slug', 'banquet-server')->firstOrFail();
        $planoRate = $plano->currentRateFor($serverPosition->id);
        WorkOrder::create([
            'person_id' => $hired->id,
            'property_id' => $plano->id,
            'position_id' => $serverPosition->id,
            'pay_rate' => $planoRate->pay_rate,
            'bill_rate' => $planoRate->bill_rate,
            'ot_pay_rate' => $planoRate->ot_pay_rate,
            'ot_bill_rate' => $planoRate->ot_bill_rate,
            'start_date' => now()->subDays(2)->toDateString(),
            'status' => WorkOrderStatus::Active,
            'source' => WorkOrderSource::RecruiterCreated,
            'created_by' => $recruiter->id,
        ]);

        ContactInquiry::create([
            'type' => 'business',
            'first_name' => 'Morgan', 'last_name' => 'Hotelier',
            'email' => 'morgan@grandhotel.example', 'phone' => '(214) 555-0400',
            'company' => 'Grand Hotel Dallas', 'inquiry_type' => 'Looking to Hire for Team',
            'message' => 'We need housekeeping coverage for the summer season.',
        ]);
        ContactInquiry::create([
            'type' => 'job_seeker',
            'first_name' => 'Casey', 'last_name' => 'Curious',
            'email' => 'casey.curious@example.com', 'phone' => '(972) 555-0410',
            'message' => 'Do you have weekend-only housekeeping shifts?',
        ]);
    }

    /**
     * An import-only hotel with a committed Excel hour import: a contractor on an
     * active WO whose weekly total is imported, producing an approved timesheet and
     * frozen invoice — so the Imports list + a real import-sourced invoice show up.
     */
    private function seedImport(Person $actor, Position $position): void
    {
        $property = Property::create([
            'name' => 'Imported Inn (Irving)',
            'pm_name' => 'Mona Manager',
            'city' => 'Irving',
            'state' => 'TX',
            'timezone' => 'America/Chicago',
            'tax_rate' => 0.0875,
            'closing_day' => 5, // week ends Friday → Sat–Fri import sheets
            'status' => PropertyStatus::Active,
            'time_source' => PropertyTimeSource::Import,
        ]);
        $property->assignments()->create(['person_id' => $actor->id, 'role' => PropertyAssignmentRole::Recruiter->value]);
        $property->positionRates()->create([
            'position_id' => $position->id, 'effective_date' => now()->subMonths(2)->toDateString(),
            'pay_rate' => 2000, 'bill_rate' => 3200, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4800,
            'is_active' => true, 'created_by' => $actor->id,
        ]);

        $weekStart = $property->weekStartFor(Carbon::now($property->timezone))->subWeeks(2);
        $period = PayrollPeriod::create([
            'property_id' => $property->id,
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->addDays(6)->toDateString(),
            'status' => PayrollPeriodStatus::Open,
        ]);

        $contractor = Person::factory()->create(['name' => 'Iris Imported', 'status' => PersonStatus::ContractorActive]);
        $contractor->syncRoles('contractor');
        PersonExternalId::create(['person_id' => $contractor->id, 'property_id' => $property->id, 'external_id' => '5001']);
        WorkOrder::create([
            'person_id' => $contractor->id, 'property_id' => $property->id, 'position_id' => $position->id,
            'pay_rate' => 2000, 'bill_rate' => 3200, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4800,
            'start_date' => $weekStart->toDateString(), 'status' => WorkOrderStatus::Active,
            'source' => WorkOrderSource::Imported, 'created_by' => $actor->id,
        ]);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Name', 'EmployeeID', 'TotalHours', 'PayRate', 'BillRate', 'StartDate', 'EndDate', 'Position'],
            ['Iris Imported', '5001', 38, 20, 32, $weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString(), $position->name],
        ], null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'seedimp').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $batch = app(CreateImportBatch::class)->handle($property, $period, $path, $actor);
        app(CommitImport::class)->handle($batch->fresh(), $actor);

        @unlink($path);
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

        // A reusable adjustment catalog (Phase 04 Inc 2).
        AdjustmentItem::create(['name' => 'Attendance Bonus', 'default_value' => 2500, 'type' => AdjustmentType::Incentive, 'is_billable' => false, 'active' => true]);
        AdjustmentItem::create(['name' => 'Mileage Reimbursement', 'default_value' => 1500, 'type' => AdjustmentType::Incentive, 'is_billable' => false, 'active' => true]);
        $uniformFee = AdjustmentItem::create(['name' => 'Uniform Replacement Fee', 'default_value' => 2000, 'type' => AdjustmentType::Deduction, 'is_billable' => false, 'active' => true]);

        // Manual adjustments on the current open period → show on the grid:
        // a free-form incentive and a catalog-backed deduction.
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
            app(CreateManualAdjustment::class)->handle($openPeriod, [
                'person_id' => $contractors[1]->id,
                'work_order_id' => $workOrders[1]->id,
                'adjustment_item_id' => $uniformFee->id,
                'value' => 2000, 'type' => 'deduction',
                'notes' => 'Lost polo replacement',
            ], $recruiter);
        }
    }

    /**
     * A completed contractor punch (clock-in + clock-out the same local day),
     * written the way ClockInContractor/ClockOutContractor write it: QR events
     * carry GPS inside the property geofence, tablet punches don't. Punch times
     * get a few minutes of human jitter so grids don't look machine-generated.
     */
    private function clockEvent(WorkOrder $workOrder, string $date, string $start, string $end, string $method = 'qr'): void
    {
        $property = $workOrder->property;
        $tz = $property->timezone;

        $startAt = Carbon::parse("{$date} {$start}", $tz)->addMinutes(random_int(-4, 7))->setTimezone('UTC');
        $endAt = Carbon::parse("{$date} {$end}", $tz)->addMinutes(random_int(-3, 9))->setTimezone('UTC');

        $period = PayrollPeriod::query()
            ->where('property_id', $property->id)
            ->whereDate('week_start', '<=', $date)
            ->whereDate('week_end', '>=', $date)
            ->firstOrFail();

        $gps = $method === 'qr' && $property->latitude !== null
            ? [
                'lat' => (float) $property->latitude + random_int(-15, 15) / 100000,
                'lng' => (float) $property->longitude + random_int(-15, 15) / 100000,
            ]
            : null;

        TimeEntry::create([
            'person_id' => $workOrder->person_id,
            'work_order_id' => $workOrder->id,
            'property_id' => $property->id,
            'payroll_period_id' => $period->id,
            'source' => TimeEntrySource::ClockEvent,
            'clock_method' => $method,
            'entry_type' => TimeEntryType::Work,
            'start_at_utc' => $startAt,
            'end_at_utc' => $endAt,
            'duration_minutes' => (int) $startAt->diffInMinutes($endAt),
            'timezone' => $tz,
            'pay_rate_snapshot' => $workOrder->pay_rate,
            'bill_rate_snapshot' => $workOrder->bill_rate,
            'ot_pay_rate_snapshot' => $workOrder->ot_pay_rate,
            'ot_bill_rate_snapshot' => $workOrder->ot_bill_rate,
            'clock_in_gps_lat' => $gps['lat'] ?? null,
            'clock_in_gps_lng' => $gps['lng'] ?? null,
            'clock_in_gps_accuracy_meters' => $gps !== null ? random_int(5, 20) : null,
            'clock_out_gps_lat' => $gps['lat'] ?? null,
            'clock_out_gps_lng' => $gps['lng'] ?? null,
            'clock_out_gps_accuracy_meters' => $gps !== null ? random_int(5, 20) : null,
        ]);

        RecomputeTimeSummary::dispatchSync($workOrder->id, $period->id);
    }

    /**
     * An in-progress clock entry (started, not ended) — feeds the live
     * "On the clock now" dashboard widget. No summary recompute: summaries
     * only materialize completed entries (clock-out does that in real life).
     */
    private function openClockEntry(WorkOrder $workOrder, int $minutesAgo, string $method = 'qr'): void
    {
        $property = $workOrder->property;
        $startAt = Carbon::now()->subMinutes($minutesAgo);

        $period = PayrollPeriod::query()
            ->where('property_id', $property->id)
            ->whereDate('week_start', '<=', $startAt->copy()->setTimezone($property->timezone)->toDateString())
            ->whereDate('week_end', '>=', $startAt->copy()->setTimezone($property->timezone)->toDateString())
            ->firstOrFail();

        TimeEntry::create([
            'person_id' => $workOrder->person_id,
            'work_order_id' => $workOrder->id,
            'property_id' => $property->id,
            'payroll_period_id' => $period->id,
            'source' => TimeEntrySource::ClockEvent,
            'clock_method' => $method,
            'entry_type' => TimeEntryType::Work,
            'start_at_utc' => $startAt,
            'end_at_utc' => null,
            'duration_minutes' => null,
            'timezone' => $property->timezone,
            'pay_rate_snapshot' => $workOrder->pay_rate,
            'bill_rate_snapshot' => $workOrder->bill_rate,
            'ot_pay_rate_snapshot' => $workOrder->ot_pay_rate,
            'ot_bill_rate_snapshot' => $workOrder->ot_bill_rate,
            'clock_in_gps_lat' => $method === 'qr' && $property->latitude !== null ? (float) $property->latitude + random_int(-15, 15) / 100000 : null,
            'clock_in_gps_lng' => $method === 'qr' && $property->longitude !== null ? (float) $property->longitude + random_int(-15, 15) / 100000 : null,
            'clock_in_gps_accuracy_meters' => $method === 'qr' ? random_int(5, 20) : null,
        ]);
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
