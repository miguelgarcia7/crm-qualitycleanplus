<?php

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoiceItem;
use App\Domain\Billing\Support\InvoicePositionSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function lineItemInvoice(): Invoice
{
    $invoice = Invoice::factory()->create();

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'contractor_name' => 'Freddy Olan',
        'position_name' => 'Barback',
        'regular_minutes' => 918,   // 15.30 h
        'overtime_minutes' => 0,
        'bill_rate' => 2100,        // $21.00
        'ot_bill_rate' => 3150,
        'total_bill' => 32130,
    ]);

    return $invoice->load('items');
}

it('sends the frozen bill rates to the invoice screen', function () {
    $invoice = lineItemInvoice();

    // The rates were always stored on the item; they were never sent to the
    // view, so the hours column could not show what they were billed at.
    $this->actingAs(person('payroll'))->get(main("/admin/invoices/{$invoice->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('invoice.items.0.bill_rate', 2100)
            ->where('invoice.items.0.ot_bill_rate', 3150)
            ->where('invoice.items.0.position_name', 'Barback'),
        );
});

it('prints the rate beside the hours on the PDF', function () {
    $invoice = lineItemInvoice();

    $html = view('pdf.invoice', ['invoice' => $invoice])->render();

    // 918 minutes at $21.00 — the hours stay decimal, the rate sits next to them.
    expect($html)->toContain('15.30')
        ->toContain('$21.00')
        ->toContain('Reg Hrs | Rate')
        // Position moved under the name rather than into its own column.
        ->toContain('Barback');

    // Assert the line-item header specifically: the per-position rollup lower
    // down still has a Position column, and legitimately so.
    $flat = (string) preg_replace('/\s+/', ' ', $html);
    expect($flat)->toContain('<th>Contractor</th> <th>Job Code</th>');
});

it('leaves the overtime rate off a line with no overtime', function () {
    $invoice = lineItemInvoice();

    $html = view('pdf.invoice', ['invoice' => $invoice])->render();

    // $31.50 would be noise on a row that worked none.
    expect($html)->not->toContain('$31.50');
});

it('shows the overtime rate when there is overtime', function () {
    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'regular_minutes' => 2400,
        'overtime_minutes' => 300,
        'bill_rate' => 2100,
        'ot_bill_rate' => 3150,
    ]);

    $html = view('pdf.invoice', ['invoice' => $invoice->load('items')])->render();

    expect($html)->toContain('$31.50')->toContain('5.00');
});

it('still renders a real PDF after the layout change', function () {
    $pdf = Pdf::loadView('pdf.invoice', ['invoice' => lineItemInvoice()])->output();

    expect(substr($pdf, 0, 5))->toBe('%PDF-');
});

// --- Position summary ----------------------------------------------------------------

it('rolls a position up with its rate and total hours', function () {
    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->count(2)->create([
        'invoice_id' => $invoice->id,
        'position_name' => 'Barback',
        'regular_minutes' => 3000,
        'overtime_minutes' => 300,
        'holiday_minutes' => 0,
        'training_minutes' => 0,
        'bill_rate' => 2100,
        'total_bill' => 50000,
    ]);

    $rows = InvoicePositionSummary::for($invoice->load('items'));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['bill_rate'])->toBe(2100)
        // Total hours covers every bucket, not just regular.
        ->and($rows[0]['total_minutes'])->toBe(6600)
        ->and($rows[0]['total_bill'])->toBe(100000);
});

it('reports a mixed rate rather than picking one', function () {
    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'position_name' => 'Food Runner', 'bill_rate' => 2100]);
    // The real case this came from: one contractor on the same position billed
    // at zero, which would otherwise be averaged away or silently hidden.
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'position_name' => 'Food Runner', 'bill_rate' => 0]);

    $rows = InvoicePositionSummary::for($invoice->load('items'));

    expect($rows[0]['bill_rate'])->toBeNull();
});

it('prints Mixed on the PDF when a position has more than one rate', function () {
    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'position_name' => 'Food Runner', 'bill_rate' => 2100]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'position_name' => 'Food Runner', 'bill_rate' => 0]);

    $html = view('pdf.invoice', ['invoice' => $invoice->load('items')])->render();

    expect($html)->toContain('Mixed')->toContain('Total Hrs');
});

it('never sends a payout figure to a property manager', function () {
    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'total_payout' => 12345]);
    $pm = person('property_manager');
    $invoice->property->assignments()->create(['person_id' => $pm->id, 'role' => 'property_manager']);

    // Neither surface shows payout any more, so this is structural — the
    // builder does not emit it at all. Pinned anyway: it is the invariant that
    // matters, not how it currently happens to be enforced.
    $this->actingAs($pm)->get(qcminute("/invoices/{$invoice->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('invoice.position_summary.0.bill_rate')
            ->has('invoice.position_summary.0.total_minutes')
            ->missing('invoice.position_summary.0.total_payout'),
        );
});

// --- Preview-only notice -------------------------------------------------------------

it('carries the preview notice onto the PDF', function () {
    $html = view('pdf.invoice', ['invoice' => lineItemInvoice()])->render();

    expect($html)->toContain('This invoice is a PREVIEW ONLY.')
        ->toContain('Final invoices are issued through QuickBooks.');
});

it('shares the notice with both invoice screens', function () {
    $invoice = lineItemInvoice();
    $pm = person('property_manager');
    $invoice->property->assignments()->create(['person_id' => $pm->id, 'role' => 'property_manager']);

    // The client reads it on QC Minute; staff need to know the same thing.
    $this->actingAs(person('payroll'))->get(main("/admin/invoices/{$invoice->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('previewNotice.heading', 'This invoice is a PREVIEW ONLY.'));

    $this->actingAs($pm)->get(qcminute("/invoices/{$invoice->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('previewNotice.body', 'Final invoices are issued through QuickBooks.'));
});

it('drops the notice everywhere when it is unset', function () {
    // One switch for the day invoices stop going through QuickBooks.
    config(['qcp.invoice.preview_notice' => null]);

    $html = view('pdf.invoice', ['invoice' => lineItemInvoice()])->render();

    expect($html)->not->toContain('PREVIEW ONLY')
        // The stylesheet always carries the rule; what must be absent is the
        // rendered block.
        ->not->toContain('class="preview-notice"');
});

// --- Totals block --------------------------------------------------------------------

it('sends the tax rate to both screens, not just the amount', function () {
    $invoice = Invoice::factory()->create([
        'work_subtotal' => 836922,
        'adjustment_total' => 0,
        'subtotal' => 836922,
        'tax_rate' => 0.0825,
        'tax_amount' => 69046,
        'total' => 905968,
    ]);
    $pm = person('property_manager');
    $invoice->property->assignments()->create(['person_id' => $pm->id, 'role' => 'property_manager']);

    // The PDF always printed the rate; the screens showed only the amount, so a
    // $0.00 could not be told apart from a broken calculation.
    $this->actingAs(person('payroll'))->get(main("/admin/invoices/{$invoice->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('invoice.tax_rate', 0.0825)
            ->where('invoice.work_subtotal', 836922)
            ->where('invoice.adjustment_total', 0));

    $this->actingAs($pm)->get(qcminute("/invoices/{$invoice->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('invoice.tax_rate', 0.0825)
            ->where('invoice.work_subtotal', 836922));
});

it('prints the whole totals ladder on the PDF', function () {
    $invoice = Invoice::factory()->create([
        'work_subtotal' => 836922,
        'adjustment_total' => 0,
        'subtotal' => 836922,
        'tax_rate' => 0.0825,
        'tax_amount' => 69046,
        'total' => 905968,
    ]);

    $html = view('pdf.invoice', ['invoice' => $invoice->load('items')])->render();

    expect($html)->toContain('Work Subtotal')
        ->toContain('Adjustments')
        ->toContain('Tax Rate')
        ->toContain('Total Tax')
        // Trailing zeros trimmed: 8.25%, not 8.2500%.
        ->toContain('8.25%')
        ->toContain('$690.46');
});

it('shows a zero rate as 0%, not as a blank', function () {
    $invoice = Invoice::factory()->create(['tax_rate' => 0, 'tax_amount' => 0]);

    expect(view('pdf.invoice', ['invoice' => $invoice->load('items')])->render())->toContain('0%');
});

it('signs an adjustment so a credit reads as a credit', function () {
    $invoice = Invoice::factory()->create(['adjustment_total' => -5000]);

    expect(view('pdf.invoice', ['invoice' => $invoice->load('items')])->render())->toContain('-$50.00');
});

it('keeps payout off the back-office invoice too', function () {
    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'total_payout' => 12345]);

    // An invoice is a billing document. Margin lives in the reports built for
    // it, not on the page someone may be sharing their screen from.
    $this->actingAs(person('payroll'))->get(main("/admin/invoices/{$invoice->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('invoice.position_summary.0.total_bill')
            ->missing('invoice.position_summary.0.total_payout'));
});
