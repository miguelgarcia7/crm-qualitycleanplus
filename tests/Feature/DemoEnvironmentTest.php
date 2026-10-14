<?php

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Demo\DemoRoster;
use App\Domain\FieldVisits\Enums\FieldVisitStatus;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\Imports\Enums\ImportBatchStatus;
use App\Domain\Imports\Models\ImportBatch;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\Marketing\Models\ContactInquiry;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Models\PersonExternalId;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Enums\PropertyTimeSource;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Recruiting\Models\JobPosting;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/** Thursday 2026-10-01, 10:00 in Chicago — mid-shift, mid-week. */
function seedDemoAt(string $localTime = '2026-10-01 10:00'): void
{
    Storage::fake((string) config('filesystems.default'));
    // The structural seeders demo:reset runs before the demo itself.
    test()->seed([RolePermissionSeeder::class, PositionSeeder::class, HolidaySeeder::class, InventorySeeder::class]);
    test()->travelTo(CarbonImmutable::parse($localTime, 'America/Chicago')->utc());
    test()->seed(DemoSeeder::class);
}

function demoProperty(string $name): Property
{
    return Property::query()->where('name', $name)->firstOrFail();
}

function openEntries(): int
{
    return TimeEntry::query()->whereNotNull('start_at_utc')->whereNull('end_at_utc')->count();
}

it('seeds a login for every role, a recruiter and manager per company, and their contractors', function () {
    seedDemoAt();

    foreach (array_unique(array_column(DemoRoster::STAFF, 0)) as $role) {
        expect(Person::role($role)->count())->toBeGreaterThan(0, $role);
    }
    expect(Person::role('recruiter')->count())->toBe(2)
        ->and(Person::role('property_manager')->count())->toBe(4)
        ->and(Person::role('contractor')->count())->toBe(14);

    $acme = demoProperty('Acme Hotel');
    expect($acme->workOrders()->where('status', WorkOrderStatus::Active)->count())->toBe(5)
        ->and($acme->workOrders()->distinct()->count('pay_rate'))->toBeGreaterThan(3);

    foreach (['QCP Property' => 'pm.qcp@example.com', 'MAG Solutions' => 'pm.mag@example.com'] as $name => $pm) {
        $property = demoProperty($name);
        $assigned = fn (PropertyAssignmentRole $role) => $property->assignments()->where('role', $role->value)->with('person')->first()?->person?->email;

        expect($property->workOrders()->where('status', WorkOrderStatus::Active)->count())->toBe(2)
            ->and($property->workOrders()->distinct()->count('position_id'))->toBe(2)
            ->and($assigned(PropertyAssignmentRole::Recruiter))->toBe('recruiter2@example.com')
            ->and($assigned(PropertyAssignmentRole::PropertyManager))->toBe($pm);
    }
});

it('gives the new companies a rate history and each of their contractors a pay raise', function () {
    seedDemoAt();

    // Two Bible rates per position: the old one closed the day before the new.
    $rates = demoProperty('QCP Property')->positionRates()->orderBy('effective_date')->get()->groupBy('position_id');
    expect($rates)->toHaveCount(2);
    foreach ($rates as $history) {
        expect($history)->toHaveCount(2)
            ->and($history[0]->end_date->toDateString())->toBe($history[1]->effective_date->subDay()->toDateString())
            ->and($history[1]->end_date)->toBeNull()
            ->and($history[1]->pay_rate)->toBeGreaterThan($history[0]->pay_rate);
    }

    // Carlos: hired at $14.00, raised to $15.50 on Monday 2026-08-31.
    $carlos = Person::query()->where('email', 'carlos.mendez@example.com')->firstOrFail();
    [$old, $new] = WorkOrder::query()->where('person_id', $carlos->id)->orderBy('id')->get()->all();

    expect($old->status)->toBe(WorkOrderStatus::Closed)
        ->and($old->end_date->toDateString())->toBe('2026-08-30')
        ->and($new->source)->toBe(WorkOrderSource::PayIncrease)
        ->and($new->parent_wo_id)->toBe($old->id)
        ->and($new->start_date->toDateString())->toBe('2026-08-31')
        ->and([$old->pay_rate, $new->pay_rate])->toBe([1400, 1550]);

    // Hours on both sides of the raise, each at its own rate.
    $snapshots = TimeEntry::query()->where('person_id', $carlos->id)->get()
        ->groupBy('work_order_id')->map(fn ($entries) => $entries->pluck('pay_rate_snapshot')->unique()->values()->all());
    expect($snapshots[$old->id])->toBe([1400])
        ->and($snapshots[$new->id])->toBe([1550]);
});

it('seeds three job postings and three applicants at different stages', function () {
    seedDemoAt();

    expect(JobPosting::query()->count())->toBe(3)
        ->and(JobPosting::query()->published()->count())->toBe(2)
        ->and(JobApplication::query()->pluck('status')->map->value->sort()->values()->all())
        ->toBe(['rejected', 'reviewing', 'submitted'])
        ->and(Person::query()->where('status', PersonStatus::Applicant)->count())->toBe(3);
});

it('replays six weeks so only the long-shift contractors run into overtime', function () {
    seedDemoAt();
    // James punches long days at Acme; Daniel's imported weeks at Lakeside are long too.
    $long = Person::query()->whereIn('email', ['james.carter@example.com', 'daniel.okafor@example.com'])->pluck('id');

    $summaries = TimeSummary::query()->with('workOrder')->get();
    $overtime = $summaries->where('overtime_minutes', '>', 0);

    expect($overtime->pluck('workOrder.person_id')->unique()->sort()->values()->all())->toBe($long->sort()->values()->all())
        ->and($summaries
            ->filter(fn (TimeSummary $s) => ! $long->contains($s->workOrder->person_id))
            ->every(fn (TimeSummary $s) => $s->regular_minutes + $s->overtime_minutes + $s->holiday_minutes < 40 * 60))->toBeTrue();
});

it('leaves billing at every stage: last week with the PM, older weeks invoiced, sent and paid', function () {
    seedDemoAt();

    // Weeks close Sunday. Week of 9/21 → submitted 9/28, approval due 10/5.
    // 8/17–9/14 → invoiced + sent at each of the four companies (Lakeside's
    // by import, no PM step). Acme, QCP and Lakeside pay 22 days after close
    // (through 9/6); MAG Solutions pays after 52, so none of its are paid yet
    // and the oldest (due 9/30) is overdue.
    $mag = demoProperty('MAG Solutions');

    expect(Timesheet::query()->where('status', TimesheetStatus::PendingApproval)->count())->toBe(3)
        ->and(Invoice::query()->count())->toBe(20)
        ->and(Invoice::query()->where('status', InvoiceStatus::InvoiceSent)->count())->toBe(20)
        ->and(Invoice::query()->whereNotNull('paid_at')->count())->toBe(9)
        ->and(Invoice::query()->where('property_id', $mag->id)->whereNotNull('paid_at')->count())->toBe(0)
        ->and(Invoice::query()->where('property_id', $mag->id)->whereNull('paid_at')->whereDate('due_date', '<', '2026-10-01')->count())->toBe(1);
});

it('imports Lakeside Suites’ weekly hours instead of punches, leaving last week to upload', function () {
    seedDemoAt();
    $lakeside = demoProperty('Lakeside Suites');
    $lastWeek = $lakeside->payrollPeriods()->whereDate('week_start', '2026-09-21')->firstOrFail();

    expect($lakeside->time_source)->toBe(PropertyTimeSource::Import)
        ->and($lakeside->qr_clock_enabled)->toBeFalse()
        ->and(PersonExternalId::query()->where('property_id', $lakeside->id)->pluck('external_id')->sort()->values()->all())
        ->toBe(['LS-1042', 'LS-1057', 'LS-1063', 'LS-1071', 'LS-1088'])
        // 8/17–9/14, each imported the Monday after the next week closed.
        ->and(ImportBatch::query()->where('property_id', $lakeside->id)->where('status', ImportBatchStatus::Applied)->count())->toBe(5)
        ->and(TimeEntry::query()->where('property_id', $lakeside->id)->whereNotNull('start_at_utc')->count())->toBe(0)
        ->and($lastWeek->status)->toBe(PayrollPeriodStatus::Open)
        ->and(ImportBatch::query()->where('payroll_period_id', $lastWeek->id)->exists())->toBeFalse()
        // Labor Day week: weekly totals are never holiday pay.
        ->and(TimeSummary::query()->where('property_id', $lakeside->id)->sum('holiday_minutes'))->toBe(0);

    // A week later the simulator imports 9/21 itself, and only once.
    config(['demo.enabled' => true]);
    $this->travelTo(CarbonImmutable::parse('2026-10-05 11:00', 'America/Chicago')->utc());
    $this->artisan('demo:simulate')->assertSuccessful();
    $this->artisan('demo:simulate')->assertSuccessful();

    expect(ImportBatch::query()->where('payroll_period_id', $lastWeek->id)->where('status', ImportBatchStatus::Applied)->count())->toBe(1);
});

it('keeps contractors on the clock and punches them out on plan, idempotently', function () {
    seedDemoAt();
    config(['demo.enabled' => true]);

    expect(openEntries())->toBe(9); // everyone mid-morning shift

    $this->travelTo(CarbonImmutable::parse('2026-10-01 16:30', 'America/Chicago')->utc());
    $this->artisan('demo:simulate')->assertSuccessful();

    $maria = TimeEntry::query()
        ->whereHas('person', fn ($q) => $q->where('email', 'maria.lopez@example.com'))
        ->latest('start_at_utc')
        ->firstOrFail();
    $clockedOut = CarbonImmutable::parse($maria->end_at_utc)->setTimezone('America/Chicago');

    expect(openEntries())->toBe(1) // James works until ~5:30
        ->and($clockedOut->format('H:i') >= '15:55' && $clockedOut->format('H:i') <= '16:05')->toBeTrue();

    $count = TimeEntry::query()->count();
    $this->artisan('demo:simulate')->assertSuccessful();
    expect(TimeEntry::query()->count())->toBe($count);
});

it('seeds the activity the dashboards show: requests, time off, uniforms, inquiries and visits', function () {
    seedDemoAt();

    // One of each request, waiting on someone.
    expect(MoreStaffRequest::query()->where('status', MoreStaffStatus::Submitted)->count())->toBe(1)
        ->and(Workflow::query()->where('type', WorkflowType::PayIncrease->value)->where('status', WorkflowStatus::InProgress)->count())->toBe(1)
        ->and(Workflow::query()->where('type', WorkflowType::ChangePersonalInfo->value)->where('status', WorkflowStatus::InProgress)->count())->toBe(1);

    // Time off: two waiting, two approved — and the hours came out of the balances.
    expect(PtoRequest::query()->where('status', PtoRequestStatus::Pending)->count())->toBe(2)
        ->and(PtoRequest::query()->where('status', PtoRequestStatus::Approved)->count())->toBe(2);

    // Uniforms: mediums low, more on order, two requests to fill.
    $medium = ItemVariant::query()->where('size', 'M')->firstOrFail();
    expect($medium->current_stock)->toBe(3)
        ->and($medium->reorder_threshold)->toBe(10)
        ->and(PurchaseOrder::query()->count())->toBe(1)
        ->and(SupplyRequest::query()->pending()->count())->toBe(2)
        ->and(SupplyRequest::query()->where('quantity', 4)->exists())->toBeTrue();

    // Website inquiries and field visits.
    expect(ContactInquiry::query()->whereNull('handled_at')->pluck('type')->sort()->values()->all())->toBe(['business', 'job_seeker', 'job_seeker'])
        ->and(FieldVisit::query()->where('status', FieldVisitStatus::Closed)->count())->toBe(2)
        ->and(FieldVisit::query()->where('property_id', demoProperty('MAG Solutions')->id)->exists())->toBeFalse();
});

it('opens every back-office dashboard on the demo data', function () {
    seedDemoAt();

    foreach ([
        'admin@example.com' => 'admin/dashboard/admin-today',
        'recruiter@example.com' => 'admin/dashboard/recruiter',
        'payroll@example.com' => 'admin/dashboard/payroll',
        'hr@example.com' => 'admin/dashboard/hr',
        'office-manager@example.com' => 'admin/dashboard/office',
        'front-desk@example.com' => 'admin/dashboard/front-desk',
    ] as $email => $component) {
        $this->actingAs(Person::query()->where('email', $email)->firstOrFail())
            ->get(main('/admin/dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component($component));
    }

    foreach (['analytics', 'workforce'] as $view) {
        $this->actingAs(Person::query()->where('email', 'admin@example.com')->firstOrFail())
            ->get(main("/admin/dashboard?view={$view}"))
            ->assertOk();
    }
});

it('only simulates in demo mode', function () {
    config(['demo.enabled' => false]);

    $this->artisan('demo:simulate')->assertFailed();
});

it('refuses to reset a production database', function () {
    config(['demo.enabled' => true]);
    $this->app['env'] = 'production';

    $this->artisan('demo:reset', ['--force' => true])->assertFailed();
});

it('refuses to reset outside demo mode, whatever the environment is called', function () {
    config(['demo.enabled' => false]);
    $this->app['env'] = 'staging';

    $this->artisan('demo:reset', ['--force' => true])->assertFailed();
});

it('marks every response noindex in demo mode', function () {
    config(['demo.enabled' => true]);

    $this->get(main('/'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
