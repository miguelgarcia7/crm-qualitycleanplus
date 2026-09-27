<?php

use App\Domain\Billing\Actions\MarkInvoicePaid;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets payroll and admin record a payment, and nobody else', function (string $role, bool $allowed) {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::InvoiceSent]);

    $response = $this->actingAs(person($role))->post(main("/admin/invoices/{$invoice->id}/paid"));

    $allowed ? $response->assertRedirect() : $response->assertForbidden();
    expect($invoice->fresh()->paid_at !== null)->toBe($allowed);
})->with([
    ['payroll', true],
    ['admin', true],
    // Sending an invoice and recording that it was settled are different jobs.
    ['recruiter', false],
    ['office_manager', false],
    ['property_manager', false],
]);

it('records who marked it and when, in the activity log', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::InvoiceSent]);
    $payroll = person('payroll');

    $this->actingAs($payroll)->post(main("/admin/invoices/{$invoice->id}/paid"))->assertRedirect();

    $invoice->refresh();

    expect($invoice->paid_at)->not->toBeNull()
        ->and($invoice->paid_by)->toBe($payroll->id);

    $log = Activity::query()->latest('id')->first();
    expect($log->description)->toBe("Marked invoice {$invoice->invoice_number} paid")
        ->and($log->causer_id)->toBe($payroll->id);
});

it('does not rewrite the recorded date when clicked again', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::InvoiceSent]);
    $payroll = person('payroll');

    app(MarkInvoicePaid::class)->handle($invoice, $payroll);
    $first = $invoice->fresh()->paid_at;

    $this->travel(2)->days();
    app(MarkInvoicePaid::class)->handle($invoice->fresh(), person('admin'));

    // Idempotent: a second click must not move the date someone recorded.
    expect($invoice->fresh()->paid_at->toDateTimeString())->toBe($first->toDateTimeString())
        ->and($invoice->fresh()->paid_by)->toBe($payroll->id);
});

it('can be undone, because a mis-click is not a void', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::InvoiceSent]);
    $payroll = person('payroll');

    $this->actingAs($payroll)->post(main("/admin/invoices/{$invoice->id}/paid"));
    expect($invoice->fresh()->paid_at)->not->toBeNull();

    $this->actingAs($payroll)->delete(main("/admin/invoices/{$invoice->id}/paid"))->assertRedirect();

    expect($invoice->fresh()->paid_at)->toBeNull()
        ->and($invoice->fresh()->paid_by)->toBeNull()
        ->and(Activity::query()->latest('id')->first()->description)
        ->toBe("Marked invoice {$invoice->invoice_number} unpaid");
});

it('refuses to mark a voided invoice paid', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Voided]);

    // A voided invoice is not owed, so there is nothing to settle.
    expect(fn () => app(MarkInvoicePaid::class)->handle($invoice, person('payroll')))
        ->toThrow(ValidationException::class);

    expect($invoice->fresh()->paid_at)->toBeNull();
});

it('derives overdue rather than storing it', function () {
    $paid = Invoice::factory()->create(['due_date' => now()->subWeek(), 'paid_at' => now()]);
    $overdue = Invoice::factory()->create(['due_date' => now()->subWeek(), 'paid_at' => null]);
    $future = Invoice::factory()->create(['due_date' => now()->addWeek(), 'paid_at' => null]);
    $voided = Invoice::factory()->create(['due_date' => now()->subWeek(), 'status' => InvoiceStatus::Voided]);

    expect($paid->isOverdue())->toBeFalse()
        ->and($overdue->isOverdue())->toBeTrue()
        ->and($future->isOverdue())->toBeFalse()
        // Voided invoices are not owed, so they are never overdue.
        ->and($voided->isOverdue())->toBeFalse();
});

it('filters the list by payment state', function () {
    $property = Property::factory()->create();
    Invoice::factory()->create(['property_id' => $property->id, 'paid_at' => now(), 'due_date' => now()->subWeek()]);
    Invoice::factory()->count(2)->create(['property_id' => $property->id, 'paid_at' => null, 'due_date' => now()->subWeek()]);
    Invoice::factory()->create(['property_id' => $property->id, 'paid_at' => null, 'due_date' => now()->addWeek()]);

    $count = function (string $query): int {
        $rows = 0;
        test()->actingAs(person('payroll'))->get(main('/admin/invoices'.$query))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$rows) {
                $rows = count($page->toArray()['props']['invoices']);
            });

        return $rows;
    };

    expect($count('?payment=paid'))->toBe(1)
        ->and($count('?payment=unpaid'))->toBe(3)
        // Overdue is unpaid AND past due — not the same as unpaid.
        ->and($count('?payment=overdue'))->toBe(2)
        ->and($count('?payment=nonsense'))->toBe(4);
});

it('offers the mark-paid action only to roles that hold the permission', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::InvoiceSent]);

    $this->actingAs(person('payroll'))->get(main("/admin/invoices/{$invoice->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.markPaid', true));

    // office_manager rather than recruiter: both lack invoices.mark_paid, but a
    // recruiter unassigned to the property cannot open the invoice at all.
    $this->actingAs(person('office_manager'))->get(main("/admin/invoices/{$invoice->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.markPaid', false));
});
