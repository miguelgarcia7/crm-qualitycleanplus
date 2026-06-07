<?php

use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Notifications\ContractExpiringNotification;
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

it('lets payroll upload a contract document', function () {
    Storage::fake('local');
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
        ->and($contract->file)->not->toBeNull();
    Storage::disk('local')->assertExists($contract->file->path);
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
