<?php

use App\Domain\Marketing\Models\ContactInquiry;
use App\Notifications\ContactInquiryReceived;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('lists every inquiry, newest first, for someone who manages them', function () {
    $old = ContactInquiry::factory()->create(['created_at' => now()->subDay()]);
    $new = ContactInquiry::factory()->business()->create();

    $this->actingAs(person('recruiter'))->get(main('/admin/inquiries'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/inquiries/index')
            ->has('inquiries', 2)
            ->where('inquiries.0.id', $new->id)
            ->where('inquiries.0.type', 'business')
            ->where('inquiries.1.id', $old->id)
            ->where('open', null));
});

it('opens the inquiry named in the link from the lead email', function () {
    $inquiry = ContactInquiry::factory()->create();

    $this->actingAs(person('office_manager'))->get(main('/admin/inquiries?open='.$inquiry->id))
        ->assertInertia(fn ($page) => $page->where('open', $inquiry->id));
});

it('keeps the inbox from roles that do not handle website leads', function () {
    $inquiry = ContactInquiry::factory()->create();

    $this->actingAs(person('payroll'))->get(main('/admin/inquiries'))->assertForbidden();
    $this->actingAs(person('payroll'))->patch(main("/admin/inquiries/{$inquiry->id}"), ['handled' => true])->assertForbidden();
    $this->actingAs(person('payroll'))->delete(main("/admin/inquiries/{$inquiry->id}"))->assertForbidden();
});

it('marks an inquiry handled, recording who, and back to new', function () {
    $inquiry = ContactInquiry::factory()->create();
    $staff = person('office_manager');

    $this->actingAs($staff)->patch(main("/admin/inquiries/{$inquiry->id}"), ['handled' => true])->assertRedirect();
    expect($inquiry->refresh()->handled_at)->not->toBeNull()
        ->and($inquiry->handled_by_id)->toBe($staff->id);

    $this->actingAs($staff)->patch(main("/admin/inquiries/{$inquiry->id}"), ['handled' => false])->assertRedirect();
    expect($inquiry->refresh()->handled_at)->toBeNull()
        ->and($inquiry->handled_by_id)->toBeNull();
});

it('deletes an inquiry', function () {
    $inquiry = ContactInquiry::factory()->create();

    $this->actingAs(person('admin'))->delete(main("/admin/inquiries/{$inquiry->id}"))->assertRedirect();

    expect(ContactInquiry::query()->count())->toBe(0);
});

it('stores the site language and spam check with each lead, and links the email to it', function () {
    Notification::fake();
    config(['qcp.marketing.contact_recipients.business' => ['sales@example.com']]);

    $this->post(main('/es/contactenos/consultas-para-negocios'), [
        'contact_first_name' => 'Dana', 'contact_last_name' => 'Cole',
        'contact_email' => 'dana@hotel.example', 'contact_phone' => '214-555-0100',
    ])->assertSessionHasNoErrors();

    $inquiry = ContactInquiry::query()->firstOrFail();
    expect($inquiry->locale)->toBe('es')
        ->and($inquiry->spam_check)->toBe('Off (reCAPTCHA keys not configured)');

    Notification::assertSentOnDemand(ContactInquiryReceived::class, function (ContactInquiryReceived $n) use ($inquiry): bool {
        return $n->toMail(new AnonymousNotifiable)->actionUrl === main('/admin/inquiries?open='.$inquiry->id);
    });
});

it('grants the inbox to admins, office managers and recruiters', function () {
    expect(person('recruiter')->can('marketing.inquiries.manage'))->toBeTrue()
        ->and(person('admin')->can('marketing.inquiries.manage'))->toBeTrue()
        ->and(person('office_manager')->can('marketing.inquiries.manage'))->toBeTrue()
        ->and(person('payroll')->can('marketing.inquiries.manage'))->toBeFalse();
});
