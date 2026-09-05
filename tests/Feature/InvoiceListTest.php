<?php

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** Invoices at one property, all with the same status. */
function invoicesAt(Property $property, int $count, InvoiceStatus $status = InvoiceStatus::Invoiced): void
{
    Invoice::factory()->count($count)->create([
        'property_id' => $property->id,
        'status' => $status,
    ]);
}

/** The invoice ids on one page of the list. */
function invoiceIds(string $query = '', ?Person $as = null): array
{
    $ids = [];

    test()->actingAs($as ?? person('office_manager'))->get(main('/admin/invoices'.$query))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$ids) {
            $ids = collect($page->toArray()['props']['invoices'])->pluck('id')->all();
        });

    return $ids;
}

it('slices the list in the database rather than shipping every row', function () {
    invoicesAt(Property::factory()->create(), 30);

    $this->actingAs(person('office_manager'))->get(main('/admin/invoices'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/invoices/index')
            ->has('invoices', 25)
            ->where('pagination.total', 30)
            ->where('pagination.last_page', 2),
        );
});

it('returns a second page that neither repeats nor drops a row', function () {
    invoicesAt(Property::factory()->create(), 30);

    $first = invoiceIds();
    $second = invoiceIds('?page=2');

    expect($second)->toHaveCount(5)
        // A day's invoices share an issue date; without a stable tiebreaker
        // rows leak between pages.
        ->and(array_intersect($first, $second))->toBeEmpty()
        ->and(array_unique([...$first, ...$second]))->toHaveCount(30);
});

it('honours the per-page choice and ignores one it does not offer', function () {
    invoicesAt(Property::factory()->create(), 30);

    expect(invoiceIds('?per_page=10'))->toHaveCount(10)
        ->and(invoiceIds('?per_page=5000'))->toHaveCount(25);
});

it('filters by status, offering every status from the enum', function () {
    $property = Property::factory()->create();
    invoicesAt($property, 3, InvoiceStatus::Invoiced);
    invoicesAt($property, 2, InvoiceStatus::Voided);

    expect(invoiceIds('?status=voided'))->toHaveCount(2)
        ->and(invoiceIds('?status=invoiced'))->toHaveCount(3)
        // Not a real status — falls back to showing everything.
        ->and(invoiceIds('?status=shredded'))->toHaveCount(5);

    $this->actingAs(person('office_manager'))->get(main('/admin/invoices'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('statuses', count(InvoiceStatus::cases())));
});

it('searches on invoice number and property name', function () {
    $sunrise = Property::factory()->create(['name' => 'Sunrise Villas']);
    $harbor = Property::factory()->create(['name' => 'Harbor Point']);

    Invoice::factory()->create(['property_id' => $sunrise->id, 'invoice_number' => 'INV-9001']);
    Invoice::factory()->count(2)->create(['property_id' => $harbor->id]);

    expect(invoiceIds('?search=INV-9001'))->toHaveCount(1)
        ->and(invoiceIds('?search=Harbor'))->toHaveCount(2)
        ->and(invoiceIds('?search=nothing at all'))->toBeEmpty();
});

it('filters by property', function () {
    $sunrise = Property::factory()->create();
    $harbor = Property::factory()->create();
    invoicesAt($sunrise, 2);
    invoicesAt($harbor, 3);

    expect(invoiceIds("?property_id={$harbor->id}"))->toHaveCount(3);
});

it('orders by a whitelisted column and falls back when handed anything else', function () {
    $property = Property::factory()->create();
    foreach ([1500, 500, 2500] as $i => $total) {
        Invoice::factory()->create([
            'property_id' => $property->id,
            'total' => $total,
            'invoice_number' => 'INV-'.(9100 + $i),
        ]);
    }

    $totalsFor = function (string $query): array {
        $totals = [];
        test()->actingAs(person('office_manager'))->get(main('/admin/invoices'.$query))
            ->assertInertia(function (AssertableInertia $page) use (&$totals) {
                $totals = collect($page->toArray()['props']['invoices'])->pluck('total')->all();
            });

        return $totals;
    };

    expect($totalsFor('?sort=total&direction=asc'))->toBe([500, 1500, 2500])
        ->and($totalsFor('?sort=total&direction=desc'))->toBe([2500, 1500, 500]);

    // An unknown column must not reach the query builder.
    $this->actingAs(person('office_manager'))->get(main('/admin/invoices?sort=password&direction=sideways'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.sort', 'issue_date')
            ->where('filters.direction', 'desc'),
        );
});

it('keeps a recruiter scoped to their properties, filter dropdown included', function () {
    $assigned = Property::factory()->create(['name' => 'Assigned Place']);
    $other = Property::factory()->create(['name' => 'Someone Elses']);
    invoicesAt($assigned, 2);
    invoicesAt($other, 30);

    $recruiter = person('recruiter');
    $assigned->assignments()->create(['person_id' => $recruiter->id, 'role' => 'recruiter']);

    expect(invoiceIds('', $recruiter))->toHaveCount(2)
        // Naming another property in the URL must not widen what they see.
        ->and(invoiceIds("?property_id={$other->id}", $recruiter))->toBeEmpty();

    // The dropdown itself would otherwise leak the whole client list.
    test()->actingAs($recruiter)->get(main('/admin/invoices'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pagination.total', 2)
            ->has('properties', 1)
            ->where('properties.0.name', 'Assigned Place'),
        );
});
