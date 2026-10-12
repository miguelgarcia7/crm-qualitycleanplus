<?php

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoiceItem;
use App\Domain\Dashboards\Services\AdminAnalytics;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\TimeSummary;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    // Wednesday Oct 14, noon in Chicago.
    $this->travelTo(CarbonImmutable::parse('2026-10-14 17:00:00', 'UTC'));
});

function analytics(string $period = 'mtd'): array
{
    return app(AdminAnalytics::class)->build($period);
}

/** An invoice issued on $issued for $total, with one line billing $bill and paying out $payout. */
function billed(Property $property, string $issued, int $total, int $bill = 0, int $payout = 0, array $attributes = [], int $overtime = 0): Invoice
{
    $invoice = Invoice::factory()->create([
        'property_id' => $property->id,
        'issue_date' => $issued,
        'due_date' => CarbonImmutable::parse($issued)->addDays(30)->toDateString(),
        'total' => $total,
        ...$attributes,
    ]);

    if ($bill > 0) {
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'total_bill' => $bill,
            'total_payout' => $payout,
            'regular_minutes' => 6000 - $overtime,
            'overtime_minutes' => $overtime,
        ]);
    }

    return $invoice;
}

it('shows Today and Analytics as tabs, and opens Analytics with ?view=analytics', function () {
    $this->actingAs(person('admin'))->get(main('/admin/dashboard?view=analytics'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/dashboard/admin-analytics')
            ->where('view', 'analytics')
            ->where('tabs.0.key', 'today')
            ->where('tabs.1.key', 'analytics')
            ->where('analytics.period', 'mtd'));

    // An unknown view falls back to Today.
    $this->actingAs(person('admin'))->get(main('/admin/dashboard?view=nope'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/dashboard/admin-today')->where('view', 'today'));
});

it('compares billing so far this month with the same days last month, not all of it', function () {
    $property = Property::factory()->create();
    billed($property, '2026-10-03', 100_00);
    billed($property, '2026-09-05', 50_00);
    billed($property, '2026-09-25', 500_00); // after the same point in September
    billed($property, '2026-10-05', 999_00, attributes: ['status' => InvoiceStatus::Voided]);

    $billed = collect(analytics()['headline'])->firstWhere('key', 'billed');

    expect($billed['value'])->toBe(100.0)
        ->and($billed['note'])->toBe('+100.0% vs same point in Sep')
        ->and($billed['trend'])->toBe('up');
});

it('works out gross margin from what each invoice billed and paid out', function () {
    $property = Property::factory()->create();
    billed($property, '2026-10-02', 1000_00, bill: 1000_00, payout: 600_00);
    billed($property, '2026-09-02', 1000_00, bill: 1000_00, payout: 650_00);

    $margin = collect(analytics()['headline'])->firstWhere('key', 'margin');

    expect($margin['value'])->toBe(40.0)
        ->and($margin['note'])->toBe('+5.0 pts vs same point in Sep');
});

it('splits what is still owed by how late it is and names the properties most behind', function () {
    $harbor = Property::factory()->create(['name' => 'Harbor Point']);
    $acme = Property::factory()->create(['name' => 'Acme Hotel']);

    billed($harbor, '2026-09-01', 300_00, attributes: ['due_date' => '2026-10-04']);   // 10 days late
    billed($harbor, '2026-08-01', 200_00, attributes: ['due_date' => '2026-08-30']);   // 45 days late
    billed($acme, '2026-10-01', 400_00, attributes: ['due_date' => '2026-10-31']);     // not due
    billed($acme, '2026-08-01', 900_00, attributes: ['due_date' => '2026-08-30', 'paid_at' => now()]);

    $owed = analytics()['owed'];
    $buckets = collect($owed['buckets'])->pluck('amount', 'key');

    expect($owed['total'])->toBe(900.0)
        ->and($buckets->all())->toBe(['current' => 400.0, 'late30' => 300.0, 'late60' => 200.0, 'late90' => 0.0])
        ->and($owed['late'])->toBe([['id' => $harbor->id, 'name' => 'Harbor Point', 'count' => 2, 'amount' => 500.0]]);

    $still = collect(analytics()['headline'])->firstWhere('key', 'owed');
    expect($still['note'])->toBe('$500.00 overdue · 2 invoices');
});

it('charts six months from the same invoices the headline reads', function () {
    $property = Property::factory()->create();
    billed($property, '2026-10-02', 1000_00, bill: 1000_00, payout: 600_00);
    billed($property, '2026-07-10', 800_00, bill: 800_00, payout: 500_00);

    $months = collect(analytics()['months']);

    expect($months->pluck('label')->all())->toBe(['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'])
        ->and($months->last())->toBe(['label' => 'Oct', 'revenue' => 1000.0, 'payouts' => 600.0, 'partial' => true])
        ->and($months->firstWhere('label', 'Jul')['revenue'])->toBe(800.0)
        ->and(collect(analytics()['headline'])->firstWhere('key', 'billed')['value'])->toBe(1000.0);
});

it('lists each property with hours, margin and overtime share for the period picked', function () {
    $acme = Property::factory()->create(['name' => 'Acme Hotel']);
    billed($acme, '2026-09-12', 1000_00, bill: 1000_00, payout: 700_00, overtime: 600);
    billed($acme, '2026-10-02', 5000_00, bill: 5000_00, payout: 3000_00);

    $data = analytics('last_month');

    expect($data['periodLabel'])->toBe('September')
        ->and($data['properties'])->toBe([[
            'id' => $acme->id,
            'name' => 'Acme Hotel',
            'hours' => 100,
            'billed' => 1000.0,
            'margin' => 30.0,
            'overtime' => 10.0,
        ]]);
});

it('adds up hours per week with overtime apart, and compares complete weeks only', function () {
    TimeSummary::factory()->create(['week_start' => '2026-09-28', 'week_end' => '2026-10-04', 'regular_minutes' => 5400, 'overtime_minutes' => 600]);
    TimeSummary::factory()->create(['week_start' => '2026-08-24', 'week_end' => '2026-08-30', 'regular_minutes' => 6000, 'overtime_minutes' => 0]);
    TimeSummary::factory()->create(['week_start' => '2026-10-12', 'week_end' => '2026-10-18', 'regular_minutes' => 600, 'overtime_minutes' => 600]);

    $weeks = analytics()['weeks'];
    $rows = collect($weeks['rows'])->keyBy('label');

    expect($weeks['rows'])->toHaveCount(8)
        ->and($rows['Sep 28'])->toMatchArray(['regular' => 90, 'overtime' => 10, 'share' => 10.0])
        ->and($rows['Oct 12']['partial'])->toBeTrue()
        ->and($weeks['latestLabel'])->toBe('Sep 28')
        ->and($weeks['latestShare'])->toBe(10.0)
        ->and($weeks['firstLabel'])->toBe('Aug 24')
        ->and($weeks['firstShare'])->toBe(0.0);
});
