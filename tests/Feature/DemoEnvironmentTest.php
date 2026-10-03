<?php

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Demo\DemoRoster;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use Carbon\CarbonImmutable;
use Database\Seeders\AcmeHotelSeeder;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;

/** Thursday 2026-10-01, 10:00 in Chicago — mid-shift, mid-week. */
function seedDemoAt(string $localTime = '2026-10-01 10:00'): Property
{
    Storage::fake((string) config('filesystems.default'));
    test()->seed([RolePermissionSeeder::class, PositionSeeder::class, HolidaySeeder::class]);
    test()->travelTo(CarbonImmutable::parse($localTime, 'America/Chicago')->utc());
    test()->seed(AcmeHotelSeeder::class);

    return Property::query()->where('name', DemoRoster::PROPERTY)->firstOrFail();
}

function openEntries(): int
{
    return TimeEntry::query()->whereNull('end_at_utc')->count();
}

it('seeds one login per role and five contractors on Acme work orders', function () {
    $property = seedDemoAt();

    foreach (array_keys(DemoRoster::STAFF) as $role) {
        expect(Person::role($role)->count())->toBe(1, $role);
    }
    expect(Person::role('contractor')->count())->toBe(5)
        ->and($property->workOrders()->count())->toBe(5)
        ->and($property->workOrders()->distinct()->count('pay_rate'))->toBeGreaterThan(3);
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
    expect(Timesheet::query()->where('status', TimesheetStatus::PendingApproval)->count())->toBe(1)
        ->and(Invoice::query()->count())->toBe(5)
        ->and(Invoice::query()->where('status', InvoiceStatus::InvoiceSent)->count())->toBe(5)
        ->and(Invoice::query()->whereNotNull('paid_at')->count())->toBe(3);
});

it('keeps contractors on the clock and punches them out on plan, idempotently', function () {
    seedDemoAt();
    config(['demo.enabled' => true]);

    expect(openEntries())->toBe(5); // everyone mid-morning shift

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
