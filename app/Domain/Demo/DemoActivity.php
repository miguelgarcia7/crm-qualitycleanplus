<?php

namespace App\Domain\Demo;

use App\Domain\Demo\Concerns\TravelsInTime;
use App\Domain\Demo\Support\PlaceholderSelfie;
use App\Domain\FieldVisits\Actions\CheckInRecruiter;
use App\Domain\FieldVisits\Actions\CheckOutRecruiter;
use App\Domain\Inventory\Actions\CreateItem;
use App\Domain\Inventory\Actions\CreatePurchaseOrder;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Enums\BeneficiaryType;
use App\Domain\Inventory\Enums\SupplyRequestStatus;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Item;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\Marketing\Models\ContactInquiry;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Pto\Actions\ApprovePtoRequest;
use App\Domain\Pto\Actions\SubmitPtoRequest;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Enums\MoreStaffUrgency;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;

/**
 * The day-to-day activity the demo's dashboards need so they aren't empty: one
 * of each request (staffing, pay increase, info change), staff time off,
 * uniform stock with a supply request and a delivery on the way, website
 * inquiries, and recruiter field visits. Everything goes through the real
 * actions and is dated relative to the reset, so it reads as "4 days ago",
 * "next week" and so on. See docs/demo-guide.md.
 */
class DemoActivity
{
    use TravelsInTime;

    /** @var array<string, Person> email => person */
    private array $people;

    /** @var array<string, Property> name => property */
    private array $properties;

    private CarbonImmutable $now;

    /**
     * @param  array<string, Person>  $people  staff logins, by email
     * @param  array<string, Property>  $properties  by name
     */
    public function handle(array $people, array $properties, CarbonImmutable $now): void
    {
        $this->people = $people;
        $this->properties = $properties;
        $this->now = $now;

        $this->requests();
        $this->timeOff();
        $this->uniforms();
        $this->inquiries();
        $this->fieldVisits();
    }

    /** One of each request: staffing, a pay increase to review, an info change to verify. */
    private function requests(): void
    {
        $acme = $this->properties['Acme Hotel'];
        $pat = $this->people['pm@example.com'];

        // Pat needs two more housekeepers at Acme.
        $this->at($this->now->subDays(4)->setTime(15, 20), function () use ($acme, $pat): void {
            $request = MoreStaffRequest::create([
                'property_id' => $acme->id,
                'position_id' => Position::query()->where('slug', 'housekeeper')->value('id'),
                'quantity_requested' => 2,
                'by_date' => $this->now->addDays(10)->toDateString(),
                'urgency' => MoreStaffUrgency::Normal,
                'reason' => 'Two housekeepers leave at the end of the month, and we have a conference the week after.',
                'status' => MoreStaffStatus::Submitted,
                'initiated_by' => $pat->id,
                'assigned_recruiter_id' => $this->recruiterOf($acme)?->id,
            ]);
            $request->update(['workflow_id' => app(StartWorkflow::class)->handle(WorkflowType::MoreStaff, $request, $pat)->id]);
        });

        // Megan offers Grace a raise; Ray reviews it.
        $grace = Person::query()->where('email', 'grace.kim@example.com')->firstOrFail();
        $workOrder = WorkOrder::query()->where('person_id', $grace->id)->where('status', WorkOrderStatus::Active->value)->firstOrFail();
        $this->at($this->now->subDays(2)->setTime(10, 5), fn () => app(StartWorkflow::class)->handle(WorkflowType::PayIncrease, $grace, $this->people['pm.mag@example.com'], [
            'work_order_id' => $workOrder->id,
            'source' => 'pm',
            'pm_requested_increase_cents' => 100,
            'reason' => 'Grace runs the morning dish line on her own now.',
        ]));

        // Carlos changes his phone number from QC Minute; HR verifies it.
        $carlos = Person::query()->where('email', 'carlos.mendez@example.com')->firstOrFail();
        $this->at($this->now->subHours(3), fn () => app(StartWorkflow::class)->handle(WorkflowType::ChangePersonalInfo, $carlos, $carlos, [
            'changes' => ['phone' => '(214) 555-0191'],
            'reason' => 'New phone number.',
            'requested_by' => $carlos->id,
        ]));
    }

    /**
     * Staff time off through the real flow, so hours come out of each
     * balance: two waiting for approval, two already approved.
     */
    private function timeOff(): void
    {
        $hannah = $this->people['hr@example.com'];
        $monday = $this->now->startOfWeek(CarbonImmutable::MONDAY);

        // Waiting: Rita's week of vacation in two weeks, and a personal day for Frank.
        $this->at($this->now->subDay()->setTime(16, 40), fn () => app(SubmitPtoRequest::class)->handle($this->people['recruiter@example.com'], [
            'bucket' => 'vacation',
            'start_date' => $monday->addWeeks(2)->toDateString(),
            'end_date' => $monday->addWeeks(2)->addDays(4)->toDateString(),
            'hours' => 40,
            'reason' => 'Family trip.',
            'notice_period_warning_acknowledged' => true,
        ]));
        $this->at($this->now->subHours(5), fn () => app(SubmitPtoRequest::class)->handle($this->people['front-desk@example.com'], [
            'bucket' => 'scheduled',
            'start_date' => $monday->addWeeks(3)->addDays(4)->toDateString(),
            'end_date' => $monday->addWeeks(3)->addDays(4)->toDateString(),
            'hours' => 8,
            'reason' => 'Appointment.',
            'notice_period_warning_acknowledged' => true,
        ]));

        // Approved: a day for Ray next week (his properties need cover) and one for Olivia.
        foreach ([
            ['recruiter2@example.com', 'vacation', $monday->addWeek()->addDays(2), 'Moving apartments.'],
            ['office-manager@example.com', 'scheduled', $monday->addWeeks(3)->addDay(), 'School event.'],
        ] as [$email, $bucket, $day, $reason]) {
            $request = $this->at($this->now->subDays(5)->setTime(9, 15), fn () => app(SubmitPtoRequest::class)->handle($this->people[$email], [
                'bucket' => $bucket,
                'start_date' => $day->toDateString(),
                'end_date' => $day->toDateString(),
                'hours' => 8,
                'reason' => $reason,
                'notice_period_warning_acknowledged' => true,
            ]));
            $this->at($this->now->subDays(4)->setTime(11, 30), fn () => app(ApprovePtoRequest::class)->handle($request, $hannah));
        }
    }

    /**
     * Uniforms only: polo shirts in four sizes and aprons, with mediums running
     * low, 24 more on order, and two requests waiting to be filled.
     */
    private function uniforms(): void
    {
        $uniforms = Category::query()->where('slug', 'uniforms')->firstOrFail();
        $olivia = $this->people['office-manager@example.com'];
        $frank = $this->people['front-desk@example.com'];
        $rita = $this->people['recruiter@example.com'];

        [$polo, $apron] = $this->at($this->now->subWeeks(8), fn () => [
            app(CreateItem::class)->handle([
                'category_id' => $uniforms->id,
                'name' => 'Polo shirt',
                'description' => 'Navy QCP polo.',
                'variants' => array_map(fn (string $size): array => ['size' => $size, 'reorder_threshold' => 10], ['S', 'M', 'L', 'XL']),
            ], $olivia),
            app(CreateItem::class)->handle([
                'category_id' => $uniforms->id,
                'name' => 'Apron',
                'description' => 'Black bib apron.',
                'variants' => [['size' => 'One size', 'reorder_threshold' => 5]],
            ], $olivia),
        ]);

        $variant = fn (Item $item, string $size): ItemVariant => $item->variants()->where('size', $size)->firstOrFail();

        // Stock as it stands today. Mediums go fastest, so they're low.
        $this->at($this->now->subWeeks(7), function () use ($polo, $apron, $variant, $frank): void {
            foreach (['S' => 12, 'M' => 3, 'L' => 15, 'XL' => 14] as $size => $quantity) {
                app(ReceiveStock::class)->handle($variant($polo, $size), $quantity, 'Opening stock', $frank);
            }
            app(ReceiveStock::class)->handle($variant($apron, 'One size'), 14, 'Opening stock', $frank);
        });

        // More mediums on the way.
        $this->at($this->now->subDays(5)->setTime(13, 10), fn () => app(CreatePurchaseOrder::class)->handle([
            'notes' => 'Uniform supplier — reorder of medium polos.',
            'items' => [['item_variant_id' => $variant($polo, 'M')->id, 'quantity' => 24]],
        ], $frank));

        // Waiting to be filled: four polos for Sofia (only three in stock), an apron for Maria.
        foreach ([
            ['sofia.ramirez@example.com', $variant($polo, 'M'), 4, 'Four shirts for her banquet shifts.', 3],
            ['maria.lopez@example.com', $variant($apron, 'One size'), 1, 'Replacing a torn apron.', 1],
        ] as [$email, $item, $quantity, $purpose, $daysAgo]) {
            $this->at($this->now->subDays($daysAgo)->setTime(14, 0), function () use ($uniforms, $email, $item, $quantity, $purpose, $rita): void {
                $request = SupplyRequest::create([
                    'category_id' => $uniforms->id,
                    'item_variant_id' => $item->id,
                    'beneficiary_type' => BeneficiaryType::Contractor,
                    'beneficiary_person_id' => Person::query()->where('email', $email)->value('id'),
                    'quantity' => $quantity,
                    'purpose' => $purpose,
                    'requested_by' => $rita->id,
                    'status' => SupplyRequestStatus::Pending,
                ]);
                $request->update(['workflow_id' => app(StartWorkflow::class)->handle(WorkflowType::SupplyRequest, $request, $rita)->id]);
            });
        }
    }

    /** A business lead and two job seekers from the website, not handled yet. */
    private function inquiries(): void
    {
        $this->at($this->now->subHours(4), fn () => ContactInquiry::create([
            'type' => 'business',
            'first_name' => 'Greg',
            'last_name' => 'Holt',
            'email' => 'greg.holt@example.com',
            'phone' => '(312) 555-0199',
            'company' => 'Northgate Hotel',
            'city' => 'Evanston',
            'state' => 'IL',
            'inquiry_type' => 'Housekeeping staff',
            'message' => 'We are opening a second building in March and need about six housekeepers. Could someone call me?',
        ]));

        foreach ([['Luis', 'Ortega', 2], ['Keisha', 'Moore', 1]] as [$first, $last, $daysAgo]) {
            $this->at($this->now->subDays($daysAgo)->setTime(19, 30), fn () => ContactInquiry::create([
                'type' => 'job_seeker',
                'first_name' => $first,
                'last_name' => $last,
                'email' => strtolower("{$first}.{$last}@example.com"),
                'phone' => '(214) 555-01'.($daysAgo === 2 ? '93' : '94'),
                'call_back_time' => 'Afternoons',
                'message' => 'Looking for full-time day work. I have hotel housekeeping experience.',
            ]));
        }
    }

    /**
     * Rita checked in at Acme last week; Ray at QCP Property three weeks ago,
     * and never at MAG Solutions.
     */
    private function fieldVisits(): void
    {
        $monday = $this->now->startOfWeek(CarbonImmutable::MONDAY);

        foreach ([
            ['recruiter@example.com', 'Acme Hotel', $monday->subWeek()->addDays(2)->setTime(10, 0)],
            ['recruiter2@example.com', 'QCP Property', $monday->subWeeks(3)->addDay()->setTime(14, 0)],
        ] as [$email, $name, $at]) {
            $recruiter = $this->people[$email];
            $property = $this->properties[$name];
            $at = CarbonImmutable::parse($at->format('Y-m-d H:i'), $property->timezone);
            $gps = ['lat' => (float) $property->latitude, 'lng' => (float) $property->longitude, 'accuracy' => 12, 'gps_status' => 'ok'];

            $visit = $this->at($at, fn () => app(CheckInRecruiter::class)->handle($recruiter, $property, [...$gps, 'selfie' => PlaceholderSelfie::for($recruiter->id)]));
            $this->at($at->addMinutes(45), fn () => app(CheckOutRecruiter::class)->handle($visit, $gps));
        }
    }

    private function recruiterOf(Property $property): ?Person
    {
        return Person::query()
            ->whereHas('propertyAssignments', fn ($q) => $q->where('property_id', $property->id)->where('role', PropertyAssignmentRole::Recruiter->value))
            ->first();
    }
}
