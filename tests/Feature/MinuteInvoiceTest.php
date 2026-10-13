<?php

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoiceItem;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->property = Property::factory()->create();
    $this->pm = person('property_manager');
    $this->property->assignments()->create([
        'person_id' => $this->pm->id,
        'role' => PropertyAssignmentRole::PropertyManager->value,
    ]);
});

function issuedInvoice(Property $property, InvoiceStatus $status = InvoiceStatus::Invoiced): Invoice
{
    return Invoice::factory()->create(['property_id' => $property->id, 'status' => $status]);
}

it('lists a property manager their own property invoices', function () {
    $mine = issuedInvoice($this->property);
    $theirs = issuedInvoice(Property::factory()->create());

    $this->actingAs($this->pm)
        ->get(qcminute('/invoices'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('minute/invoices/index')
            ->where('invoices', fn ($invoices) => collect($invoices)->pluck('id')->all() === [$mine->id]));

    // And the other property's invoice is not reachable directly either.
    $this->actingAs($this->pm)->get(qcminute("/invoices/{$theirs->id}"))->assertForbidden();
});

it('hides draft invoices from the client entirely', function () {
    $draft = issuedInvoice($this->property, InvoiceStatus::Draft);

    $this->actingAs($this->pm)
        ->get(qcminute('/invoices'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('invoices', fn ($invoices) => collect($invoices)->isEmpty()));

    $this->actingAs($this->pm)->get(qcminute("/invoices/{$draft->id}"))->assertNotFound();
    $this->actingAs($this->pm)->get(qcminute("/invoices/{$draft->id}/pdf"))->assertNotFound();
});

it('never sends contractor payout figures to the property being billed', function () {
    $invoice = issuedInvoice($this->property);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'position_name' => 'Housekeeper',
        'total_bill' => 500_00,
        'total_payout' => 300_00,
    ]);

    $response = $this->actingAs($this->pm)->get(qcminute("/invoices/{$invoice->id}"))->assertOk();

    $payload = $response->viewData('page')['props']['invoice'];
    $encoded = json_encode($payload);

    expect($encoded)->not->toContain('total_payout')
        ->and($encoded)->not->toContain('30000')
        ->and($payload['position_summary'][0])->not->toHaveKey('total_payout')
        ->and($payload['position_summary'][0]['total_bill'])->toBe(500_00);
});

it('lets a property manager download the invoice PDF', function () {
    $invoice = issuedInvoice($this->property);

    $this->actingAs($this->pm)
        ->get(qcminute("/invoices/{$invoice->id}/pdf"))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('keeps a contractor out of the invoice surface', function () {
    $invoice = issuedInvoice($this->property);

    $this->actingAs(person('contractor'))
        ->get(qcminute("/invoices/{$invoice->id}"))
        ->assertForbidden();
});

it('tells the client what is paid, due and overdue, and totals the open balance', function () {
    // Due dates count from today at the property, as the app does (Invoice::isOverdue).
    $today = now($this->property->timezone)->startOfDay();
    $paid = Invoice::factory()->create(['property_id' => $this->property->id, 'status' => InvoiceStatus::InvoiceSent, 'total' => 100_00, 'due_date' => $today->copy()->addDays(5)->toDateString(), 'paid_at' => now()->subDays(3)]);
    $due = Invoice::factory()->create(['property_id' => $this->property->id, 'status' => InvoiceStatus::InvoiceSent, 'total' => 200_00, 'due_date' => $today->copy()->addDays(10)->toDateString()]);
    $late = Invoice::factory()->create(['property_id' => $this->property->id, 'status' => InvoiceStatus::InvoiceSent, 'total' => 400_00, 'due_date' => $today->copy()->subDays(4)->toDateString()]);
    Invoice::factory()->create(['property_id' => $this->property->id, 'status' => InvoiceStatus::Voided, 'total' => 800_00, 'due_date' => $today->copy()->subDays(4)->toDateString()]);

    $rows = fn ($page) => collect($page->toArray()['props']['invoices'])->keyBy('id');

    $this->actingAs($this->pm)
        ->get(qcminute('/invoices'))
        ->assertInertia(function (Assert $page) use ($paid, $due, $late, $rows) {
            $page->where('counts', ['all' => 4, 'open' => 2, 'overdue' => 1, 'paid' => 1])
                ->where('summary.open_total', 600_00)
                ->where('summary.overdue_total', 400_00)
                ->where('summary.paid_total', 100_00)
                ->where('summary.next_due', $due->due_date->toDateString());

            $byId = $rows($page);
            expect($byId[$paid->id]['paid_at'])->toBe($paid->paid_at->toDateString())
                ->and($byId[$due->id]['days_until_due'])->toBe(10)
                ->and($byId[$late->id]['is_overdue'])->toBeTrue()
                ->and($byId[$late->id]['days_overdue'])->toBe(4);
        });

    $this->actingAs($this->pm)
        ->get(qcminute('/invoices?status=overdue'))
        ->assertInertia(fn (Assert $page) => $page->has('invoices', 1)->where('invoices.0.id', $late->id));
});

it('shows holiday hours and their rate on each line, so a line adds up', function () {
    $invoice = issuedInvoice($this->property);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'bill_rate' => 26_00,
        'regular_minutes' => 30 * 60,
        'regular_amount_bill' => 780_00,
        'holiday_minutes' => 8 * 60,
        'holiday_amount_bill' => 312_00,
        'total_bill' => 1092_00,
    ]);

    $this->actingAs($this->pm)
        ->get(qcminute("/invoices/{$invoice->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoice.items.0.holiday_minutes', 8 * 60)
            ->where('invoice.items.0.holiday_rate', 39_00)
            ->where('invoice.items.0.bill_rate', 26_00));
});
