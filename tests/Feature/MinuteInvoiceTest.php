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
