<?php

use App\Domain\Billing\Actions\GenerateInvoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Actions\InviteUser;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use App\Notifications\InvoiceIssued;
use App\Notifications\PasswordResetLink;
use App\Notifications\UserInvitation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/**
 * A complete property update payload. Named for this file: Pest loads every test
 * into one namespace, so a duplicate global helper is a fatal redeclare.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function mailPropertyPayload(Property $property, array $overrides = []): array
{
    return array_merge([
        'name' => $property->name,
        'timezone' => $property->timezone,
        'geofence_radius_meters' => $property->geofence_radius_meters,
        'tax_rate' => $property->tax_rate,
        // Literals rather than reading back: these carry DB defaults the
        // factory does not populate in memory.
        'status' => 'active',
        'time_source' => 'clock_in',
    ], $overrides);
}
/** A timesheet GenerateInvoice will accept — an empty week is enough for the snapshot. */
function invoiceableTimesheet(Property $property): Timesheet
{
    $period = PayrollPeriod::factory()->create(['property_id' => $property->id]);

    return Timesheet::factory()->create([
        'property_id' => $property->id,
        'payroll_period_id' => $period->id,
    ]);
}
/** The rendered HTML of the single mail the transport is holding. */
function renderedMail(): string
{
    /** @var ArrayTransport $transport */
    $transport = Mail::mailer()->getSymfonyTransport();

    return (string) $transport->messages()[0]->getOriginalMessage()->getHtmlBody();
}

function flushMail(): void
{
    /** @var ArrayTransport $transport */
    $transport = Mail::mailer()->getSymfonyTransport();
    $transport->flush();
}

// --- Password reset: the surface the recipient can actually sign in on ---------------

it('sends a property manager a reset link for QC Minute, not the back office', function () {
    flushMail();
    $pm = person('property_manager');

    $pm->sendPasswordResetNotification('tok-123');

    $body = renderedMail();

    expect($body)->toContain('//'.config('domains.qcminute').'/reset-password/tok-123')
        // The stock notification built this from APP_URL and sent PMs to a
        // domain they cannot sign in to.
        ->not->toContain('//'.config('domains.main'));
});

it('sends back-office staff a reset link for the back office', function () {
    flushMail();
    $recruiter = person('recruiter');

    $recruiter->sendPasswordResetNotification('tok-456');

    $body = renderedMail();

    expect($body)->toContain('//'.config('domains.main').'/reset-password/tok-456')
        ->not->toContain('//'.config('domains.qcminute'));
});

it('sends a contractor to QC Minute as well', function () {
    flushMail();
    $contractor = person('contractor');

    $contractor->sendPasswordResetNotification('tok-789');

    expect(renderedMail())->toContain('//'.config('domains.qcminute').'/reset-password/');
});

it('routes the forgot-password form through the surface-aware notification', function () {
    Notification::fake();
    $pm = person('property_manager');

    $this->post(main('/forgot-password'), ['email' => $pm->email])->assertSessionHasNoErrors();

    Notification::assertSentTo($pm, PasswordResetLink::class);
});

// --- Invoice recipient: a default that exists -----------------------------------------

it('freezes the property billing email onto the invoice for the send modal', function () {
    $property = Property::factory()->create(['billing_email' => 'ap@sunrise.example']);
    $timesheet = invoiceableTimesheet($property);

    $invoice = app(GenerateInvoice::class)->handle($timesheet);

    // The send modal defaults from the snapshot; before this the key was never
    // written, so the field opened empty and was typed from memory every time.
    expect($invoice->property_snapshot['billing_email'])->toBe('ap@sunrise.example');
});

it('accepts a billing email on the property form and shows it back', function () {
    $property = Property::factory()->create();

    $this->actingAs(person('office_manager'))
        ->put(main("/admin/properties/{$property->id}"), mailPropertyPayload($property, ['billing_email' => 'ap@harbor.example']))
        ->assertSessionHasNoErrors();

    expect($property->fresh()->billing_email)->toBe('ap@harbor.example');
});

it('rejects a billing email that is not an address', function () {
    $property = Property::factory()->create();

    $this->actingAs(person('office_manager'))
        ->put(main("/admin/properties/{$property->id}"), mailPropertyPayload($property, ['billing_email' => 'not-an-email']))
        ->assertSessionHasErrors('billing_email');
});

// --- Delivery guarantees ---------------------------------------------------------------

it('queues every email whose state is already committed when it sends', function () {
    expect(new UserInvitation('tok', 'backoffice'))->toBeInstanceOf(ShouldQueue::class)
        ->and(new PasswordResetLink('tok'))->toBeInstanceOf(ShouldQueue::class);
});

it('keeps the invoice email synchronous, because the status waits on delivery', function () {
    $property = Property::factory()->create();
    $invoice = app(GenerateInvoice::class)->handle(invoiceableTimesheet($property));

    // SendInvoice marks an invoice sent only once Postmark accepts it, so this
    // one must not be queued — the guarantee depends on it throwing in-line.
    expect(new InvoiceIssued($invoice))->not->toBeInstanceOf(ShouldQueue::class);
});

it('does not leave a new account unreachable when the invite mail is queued', function () {
    Notification::fake();

    $person = app(InviteUser::class)->handle([
        'name' => 'New Recruiter',
        'email' => 'new.recruiter@example.com',
        'role' => 'recruiter',
        'property_ids' => [],
    ]);

    // The row is committed and the mail is queued separately, so a mail outage
    // delays the invite rather than losing the account.
    expect(Person::query()->whereKey($person->id)->exists())->toBeTrue();
    Notification::assertSentTo($person, UserInvitation::class);
});
