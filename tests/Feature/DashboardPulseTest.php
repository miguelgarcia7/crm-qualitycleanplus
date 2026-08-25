<?php

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Dashboards\Services\BackOfficePulse;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** Build the landing panels for a role, unscoped. */
function pulseFor(string $role): array
{
    return app(BackOfficePulse::class)->build(person($role), null);
}

function pendingTimesheet(Property $property, int $hoursWaiting, int $weeksBack = 1): Timesheet
{
    // One period per property per week — stagger so callers can add several.
    $weekStart = now()->startOfWeek()->subWeeks($weeksBack);

    $period = PayrollPeriod::factory()->create([
        'property_id' => $property->id,
        'week_start' => $weekStart->toDateString(),
        'week_end' => $weekStart->copy()->addDays(6)->toDateString(),
    ]);

    return Timesheet::factory()->create([
        'property_id' => $property->id,
        'payroll_period_id' => $period->id,
        'status' => TimesheetStatus::PendingApproval,
        'sent_for_approval_at' => now()->subHours($hoursWaiting),
    ]);
}

it('withholds company money figures from a recruiter but shows them to an office manager', function () {
    $recruiterTitles = collect(pulseFor('recruiter')['headline'])->pluck('title');
    $officeTitles = collect(pulseFor('office_manager')['headline'])->pluck('title');

    expect($recruiterTitles)->not->toContain('Gross income MTD')
        ->and($officeTitles)->toContain('Gross income MTD');
});

it('gives no pipeline or approval queue to a role without timesheet history', function () {
    $pulse = pulseFor('hr');

    expect($pulse['pipeline'])->toBeNull()
        ->and($pulse['approvals'])->toBeNull();
});

it('flags timesheets past the three-day approval SLA and leaves fresh ones alone', function () {
    $property = Property::factory()->create();
    pendingTimesheet($property, 120, 1); // 5 days — overdue
    pendingTimesheet($property, 6, 2);   // same morning — fine

    $pulse = pulseFor('office_manager');

    expect($pulse['approvals']['total'])->toBe(2);

    $overdue = collect($pulse['approvals']['rows'])->where('overdue', true);
    expect($overdue)->toHaveCount(1)
        ->and($overdue->first()['waiting'])->toBe('5d');

    $awaiting = collect($pulse['headline'])->firstWhere('title', 'Awaiting PM approval');
    expect($awaiting['value'])->toBe(2)
        ->and($awaiting['sublabel'])->toBe('1 over 3 days')
        ->and($awaiting['tone'])->toBe('warning');
});

it('counts frozen-but-unsent invoices as ready to send, not the ones already marked sent', function () {
    $property = Property::factory()->create();

    Invoice::factory()->count(2)->create([
        'property_id' => $property->id,
        'status' => InvoiceStatus::Invoiced,
        'total' => 50_00,
        'notification_sent_at' => null,
    ]);

    Invoice::factory()->create([
        'property_id' => $property->id,
        'status' => InvoiceStatus::InvoiceSent,
        'total' => 999_00,
        'notification_sent_at' => now(),
    ]);

    $pulse = pulseFor('office_manager');

    $ready = collect($pulse['headline'])->firstWhere('title', 'Ready to send');
    expect($ready['value'])->toBe(100.0)
        ->and($ready['sublabel'])->toBe('2 invoices frozen, not sent');

    $stage = collect($pulse['pipeline']['stages'])->firstWhere('key', 'ready');
    expect($stage['value'])->toBe(2);
});

it('raises the undelivered-mail alert only while the mailer cannot actually send', function () {
    Invoice::factory()->create(['notification_sent_at' => now()]);

    config()->set('mail.default', 'log');
    $logged = pulseFor('office_manager');

    expect(collect($logged['alerts'])->pluck('key'))->toContain('mail-undelivered')
        ->and(collect($logged['alerts'])->firstWhere('key', 'mail-undelivered')['meta'])->toBe('1 invoice affected');

    // The "marked sent" stage should call out that nothing actually left.
    $sentStage = collect($logged['pipeline']['stages'])->firstWhere('key', 'sent');
    expect($sentStage['note'])->toBe('0 actually delivered')
        ->and($sentStage['tone'])->toBe('danger');

    config()->set('mail.default', 'smtp');
    expect(collect(pulseFor('office_manager')['alerts'])->pluck('key'))->not->toContain('mail-undelivered');
});

it('scales the pipeline bars across counts only, so hours never skew the funnel', function () {
    $property = Property::factory()->create();
    pendingTimesheet($property, 10);

    $stages = collect(pulseFor('office_manager')['pipeline']['stages'])->keyBy('key');

    // Clocked hours is a different unit — it anchors the strip rather than
    // being compared against a handful of timesheets.
    expect($stages['clocked']['unit'])->toBe('hours')
        ->and($stages['clocked']['fill'])->toBe(1.0)
        ->and($stages['awaiting']['unit'])->toBe('count')
        ->and($stages['awaiting']['fill'])->toBeLessThanOrEqual(1.0);
});

it('lists what needs a decision, scoped to what the viewer may see', function () {
    $pulse = pulseFor('office_manager');

    // Nothing seeded that needs deciding, so the panel stays away entirely.
    expect($pulse['decisions'])->toBeNull();
});
