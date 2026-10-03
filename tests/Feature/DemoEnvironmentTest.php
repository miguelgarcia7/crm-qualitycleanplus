<?php

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Demo\DemoRoster;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Recruiting\Models\JobPosting;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;

/** Thursday 2026-10-01, 10:00 in Chicago — mid-shift, mid-week. */
function seedDemoAt(string $localTime = '2026-10-01 10:00'): void
{
    Storage::fake((string) config('filesystems.default'));
    test()->seed([RolePermissionSeeder::class, PositionSeeder::class, HolidaySeeder::class]);
    test()->travelTo(CarbonImmutable::parse($localTime, 'America/Chicago')->utc());
    test()->seed(DemoSeeder::class);
}

function demoProperty(string $name): Property
{
    return Property::query()->where('name', $name)->firstOrFail();
}

function openEntries(): int
{
    return TimeEntry::query()->whereNull('end_at_utc')->count();
}

it('seeds a login for every role, a recruiter and manager per company, and their contractors', function () {
    seedDemoAt();

    foreach (array_unique(array_column(DemoRoster::STAFF, 0)) as $role) {
        expect(Person::role($role)->count())->toBeGreaterThan(0, $role);
    }
    expect(Person::role('recruiter')->count())->toBe(2)
        ->and(Person::role('property_manager')->count())->toBe(3)
        ->and(Person::role('contractor')->count())->toBe(9);

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

it('replays six weeks so only the long-shift contractor runs into overtime', function () {
    seedDemoAt();
    $james = Person::query()->where('email', 'james.carter@example.com')->firstOrFail();

    $summaries = TimeSummary::query()->with('workOrder')->get();
    $overtime = $summaries->where('overtime_minutes', '>', 0);

    expect($overtime)->not->toBeEmpty()
        ->and($overtime->every(fn (TimeSummary $s) => $s->workOrder->person_id === $james->id))->toBeTrue()
        ->and($summaries
            ->filter(fn (TimeSummary $s) => $s->workOrder->person_id !== $james->id)
            ->every(fn (TimeSummary $s) => $s->regular_minutes + $s->overtime_minutes + $s->holiday_minutes < 40 * 60))->toBeTrue();
});

it('leaves billing at every stage: last week with the PM, older weeks invoiced, sent and paid', function () {
    seedDemoAt();

    // Weeks close Sunday. Week of 9/21 → submitted 9/28, approval due 10/5.
    // 8/17–9/14 → invoiced + sent; paid 22 days after close (through 9/6).
    // Same cadence at each of the three companies.
    expect(Timesheet::query()->where('status', TimesheetStatus::PendingApproval)->count())->toBe(3)
        ->and(Invoice::query()->count())->toBe(15)
        ->and(Invoice::query()->where('status', InvoiceStatus::InvoiceSent)->count())->toBe(15)
        ->and(Invoice::query()->whereNotNull('paid_at')->count())->toBe(9);
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
