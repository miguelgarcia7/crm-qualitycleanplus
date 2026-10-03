<?php

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Support\InvoiceSnapshot;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** Exactly what legacy QC Minute froze onto an invoice. */
function legacyPropertySnapshot(): array
{
    return [
        'id' => 17,
        'name' => 'QCP Office',
        'phone' => '214-271-5595',
        'address' => ['zip' => '75235', 'city' => 'Dallas', 'state' => 'Texas', 'street' => '1720 Regal Row Suite 126'],
    ];
}

/** Legacy's invoicer block — real values nested, siblings present but null. */
function legacyInvoicerSnapshot(): array
{
    return [
        'zip' => null,
        'city' => null,
        'name' => 'Quality Cleaning Plus, Inc',
        'phone' => '214-271-5595',
        'state' => null,
        'account' => ['name' => 'Bank of America', 'account_number' => '123456789', 'routing_number' => '987654321'],
        'address' => ['zip' => 'Suite 126', 'city' => 'Dallas', 'state' => 'Texas', 'street' => '1720 Regal Row'],
    ];
}

it('lifts a nested address into the flat shape', function () {
    $flat = InvoiceSnapshot::flatten(legacyPropertySnapshot());

    expect($flat['address'])->toBe('1720 Regal Row Suite 126')
        ->and($flat['city'])->toBe('Dallas')
        ->and($flat['state'])->toBe('Texas')
        ->and($flat['zip'])->toBe('75235')
        // Nothing is dropped.
        ->and($flat['name'])->toBe('QCP Office')
        ->and($flat['id'])->toBe(17);
});

it('prefers the nested value when the sibling key is present but null', function () {
    $flat = InvoiceSnapshot::flatten(legacyInvoicerSnapshot());

    // Legacy leaves city/state/zip as nulls beside a populated address block.
    expect($flat['city'])->toBe('Dallas')
        ->and($flat['state'])->toBe('Texas')
        ->and($flat['zip'])->toBe('Suite 126')
        ->and($flat['address'])->toBe('1720 Regal Row')
        // Unknown keys survive — the bank block is not ours to discard.
        ->and($flat['account']['name'])->toBe('Bank of America');
});

it('leaves this app\'s own flat shape untouched', function () {
    $ours = [
        'name' => 'Sunrise Villas',
        'address' => '100 Ocean Drive',
        'city' => 'Phoenix',
        'state' => 'AZ',
        'zip' => '85001',
        'billing_email' => 'ap@sunrise.example',
    ];

    expect(InvoiceSnapshot::flatten($ours))->toBe($ours);
});

it('survives a null or empty snapshot', function () {
    expect(InvoiceSnapshot::flatten(null))->toBe([])
        ->and(InvoiceSnapshot::flatten([]))->toBe([])
        ->and(InvoiceSnapshot::flatten(['name' => 'No address at all']))->toBe(['name' => 'No address at all']);
});

it('renders a PDF for a legacy-shaped invoice', function () {
    $invoice = Invoice::factory()->create([
        'property_snapshot' => legacyPropertySnapshot(),
        'invoicer_snapshot' => legacyInvoicerSnapshot(),
    ]);

    // Echoing the nested array was a fatal htmlspecialchars() error, which broke
    // the download for 1,559 of 1,565 imported invoices.
    $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice->load('items')])->output();

    expect(substr($pdf, 0, 5))->toBe('%PDF-')
        ->and(strlen($pdf))->toBeGreaterThan(1000);
});

it('prints the company address and phone under its name on the PDF', function () {
    $invoice = Invoice::factory()->create(['invoicer_snapshot' => [
        'name' => 'Quality Cleaning Plus, Inc.', 'address' => '1720 Regal Row, Suite 126', 'city' => 'Dallas',
        'state' => 'Texas', 'zip' => '75007', 'phone' => '214-271-5595', 'email' => 'billing@qualitycleanplus.com',
    ]]);

    $html = view('pdf.invoice', ['invoice' => $invoice->load('items')])->render();

    expect($html)->toContain('1720 Regal Row, Suite 126<br>')
        ->toContain('Dallas, Texas 75007<br>')
        ->toContain('214-271-5595 · billing@qualitycleanplus.com');
});

it('prints no blank lines for an invoice frozen before the company details existed', function () {
    $invoice = Invoice::factory()->create(['invoicer_snapshot' => [
        'name' => 'Quality Cleaning Plus', 'address' => '', 'city' => '', 'state' => '', 'zip' => '', 'phone' => '', 'email' => '',
    ]]);

    $html = view('pdf.invoice', ['invoice' => $invoice->load('items')])->render();

    expect($html)->toContain('Quality Cleaning Plus')
        ->not->toMatch('/<h1>Quality Cleaning Plus<\/h1>\s*<div class="muted">/');
});

it('serves the download endpoint for a legacy-shaped invoice', function () {
    $invoice = Invoice::factory()->create([
        'property_snapshot' => legacyPropertySnapshot(),
        'invoicer_snapshot' => legacyInvoicerSnapshot(),
    ]);

    $this->actingAs(person('payroll'))
        ->get(main("/admin/invoices/{$invoice->id}/pdf"))
        ->assertOk()
        ->assertDownload("{$invoice->invoice_number}.pdf");
});

it('sends the invoice screens a flat address, whatever was frozen', function () {
    $invoice = Invoice::factory()->create([
        'property_snapshot' => legacyPropertySnapshot(),
        'invoicer_snapshot' => legacyInvoicerSnapshot(),
    ]);

    // The screens read city/state/zip as siblings, so a nested snapshot showed
    // a blank address — silently, unlike the PDF.
    $this->actingAs(person('payroll'))->get(main("/admin/invoices/{$invoice->id}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('invoice.property_snapshot.city', 'Dallas')
            ->where('invoice.property_snapshot.address', '1720 Regal Row Suite 126'),
        );
});
