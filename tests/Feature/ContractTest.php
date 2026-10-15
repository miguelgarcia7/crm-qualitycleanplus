<?php

use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Notifications\ContractExpiringNotification;
use App\Notifications\ContractThreeYearsOldNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('hides contracts from an office manager but shows them to payroll', function () {
    $property = Property::factory()->create();

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertInertia(fn (Assert $page) => $page->where('can.viewContracts', false));

    $this->actingAs(person('payroll'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertInertia(fn (Assert $page) => $page->where('can.viewContracts', true));
});

it('lets payroll upload a contract document to the default disk', function () {
    // Staging and production store files in the bucket (`s3`); the server's own
    // disk is wiped on every deploy.
    config()->set('filesystems.default', 's3');
    Storage::fake('s3');
    $property = Property::factory()->create();

    $this->actingAs(person('payroll'))
        ->post(main("/admin/properties/{$property->id}/contracts"), [
            'name' => 'MSA 2026',
            'type' => 'msa',
            'effective_date' => now()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
            'document' => UploadedFile::fake()->create('msa.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect();

    $contract = Contract::firstWhere('name', 'MSA 2026');
    expect($contract)->not->toBeNull()
        ->and($contract->file->disk)->toBe('s3');
    Storage::disk('s3')->assertExists($contract->file->path);
});

it('forbids an office manager from uploading a contract', function () {
    Storage::fake('local');
    $property = Property::factory()->create();

    $this->actingAs(person('office_manager'))
        ->post(main("/admin/properties/{$property->id}/contracts"), [
            'name' => 'MSA 2026',
            'type' => 'msa',
            'document' => UploadedFile::fake()->create('msa.pdf', 200, 'application/pdf'),
        ])
        ->assertForbidden();
});

it('forbids an office manager from downloading a contract', function () {
    Storage::fake('local');
    $property = Property::factory()->create();
    $contract = Contract::factory()->for($property)->create();
    $contract->file()->create([
        'disk' => 'local',
        'path' => UploadedFile::fake()->create('c.pdf', 100)->store('contracts', 'local'),
        'original_name' => 'c.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100,
    ]);

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$property->id}/contracts/{$contract->id}/download"))
        ->assertForbidden();

    $this->actingAs(person('payroll'))
        ->get(main("/admin/properties/{$property->id}/contracts/{$contract->id}/download"))
        ->assertOk();
});

it('alerts ownership and payroll about contracts expiring in 30 or 14 days', function () {
    Notification::fake();

    $property = Property::factory()->create();
    Contract::factory()->for($property)->expiringInDays(30)->create();
    Contract::factory()->for($property)->expiringInDays(14)->create();
    Contract::factory()->for($property)->expiringInDays(7)->create();  // not a threshold
    Contract::factory()->for($property)->expiringInDays(60)->create(); // not a threshold

    $payroll = person('payroll');

    $this->artisan('contracts:expiration-check')->assertSuccessful();

    // Two contracts hit a threshold → payroll notified twice.
    Notification::assertSentToTimes($payroll, ContractExpiringNotification::class, 2);
});

it('does not alert when no contract is near a threshold', function () {
    Notification::fake();

    $property = Property::factory()->create();
    Contract::factory()->for($property)->expiringInDays(45)->create();

    person('payroll');

    $this->artisan('contracts:expiration-check')->assertSuccessful();

    Notification::assertNothingSent();
});

it('tells Admin once when a contract turns three years old, active or replaced', function () {
    Notification::fake();
    $this->travelTo('2026-10-14 12:00:00'); // 7 AM Central

    $property = Property::factory()->create(['name' => 'Acme Hotel']);
    $today = Contract::factory()->for($property)->create(['name' => 'Rate agreement', 'effective_date' => '2023-10-14', 'expiration_date' => null, 'is_active' => true]);
    $replaced = Contract::factory()->for($property)->create(['effective_date' => '2023-10-10', 'expiration_date' => null, 'is_active' => false]); // a run missed this week
    $noDate = Contract::factory()->for($property)->create(['effective_date' => null, 'expiration_date' => null]);
    $noDate->forceFill(['created_at' => '2023-10-14 15:00:00'])->save();
    Contract::factory()->for($property)->create(['effective_date' => '2023-10-15', 'expiration_date' => null]); // tomorrow
    Contract::factory()->for($property)->create(['effective_date' => '2021-01-01', 'expiration_date' => null]); // long past: no burst

    $admin = person('admin');
    $payroll = person('payroll');

    $this->artisan('contracts:expiration-check')->assertSuccessful();

    Notification::assertSentToTimes($admin, ContractThreeYearsOldNotification::class, 3);
    Notification::assertNotSentTo($payroll, ContractThreeYearsOldNotification::class);
    Notification::assertSentTo($admin, ContractThreeYearsOldNotification::class,
        fn (ContractThreeYearsOldNotification $n) => $n->contract->is($today)
            && $n->toArray($admin)['message'] === 'Contract "Rate agreement" (Acme Hotel) is 3 years old (since Oct 14, 2023). Time to review it.');
    expect($replaced->fresh()->three_year_notice_at)->not->toBeNull();

    // Sent once: the next night says nothing more.
    $this->travelTo('2026-10-15 12:00:00');
    Notification::fake();
    $this->artisan('contracts:expiration-check')->assertSuccessful();
    Notification::assertSentToTimes($admin, ContractThreeYearsOldNotification::class, 1); // only the 10/15 one
});
