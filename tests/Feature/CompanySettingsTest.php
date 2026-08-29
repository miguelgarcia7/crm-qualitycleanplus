<?php

use App\Domain\Billing\Actions\GenerateInvoice;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\Support\CompanySettings;
use App\Domain\Time\Models\PayrollPeriod;
use Database\Seeders\CompanySettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Setting::flushMemo();
});

it('falls back to the configured defaults until something is stored', function () {
    config()->set('qcp.invoicer.name', 'Quality Cleaning Plus');
    config()->set('qcp.invoicer.city', 'Dallas');

    $invoicer = app(CompanySettings::class)->invoicer();

    expect($invoicer['name'])->toBe('Quality Cleaning Plus')
        ->and($invoicer['city'])->toBe('Dallas');
});

it('prefers the stored value over the environment default once seeded and edited', function () {
    config()->set('qcp.invoicer.name', 'From The Environment');
    $this->seed(CompanySettingsSeeder::class);

    app(CompanySettings::class)->updateInvoicer(['name' => 'Quality Cleaning Plus LLC']);
    Setting::flushMemo();

    // Even if the environment changes underneath, the stored value wins.
    config()->set('qcp.invoicer.name', 'Something Else Entirely');

    expect(app(CompanySettings::class)->invoicer()['name'])->toBe('Quality Cleaning Plus LLC');
});

it('does not overwrite an edited value when the seeder runs again', function () {
    config()->set('qcp.invoicer.city', 'Dallas');
    $this->seed(CompanySettingsSeeder::class);

    app(CompanySettings::class)->updateInvoicer(['city' => 'Fort Worth']);
    Setting::flushMemo();

    $this->seed(CompanySettingsSeeder::class);
    Setting::flushMemo();

    expect(app(CompanySettings::class)->invoicer()['city'])->toBe('Fort Worth');
});

it('reports which fields are blank, since blanks freeze onto invoices', function () {
    foreach (array_keys(CompanySettings::INVOICER_FIELDS) as $key) {
        config()->set(str_replace('invoicer.', 'qcp.invoicer.', $key), '');
    }
    config()->set('qcp.invoicer.name', 'Quality Cleaning Plus');

    expect(app(CompanySettings::class)->missingInvoicerFields())
        ->toBe(['address', 'city', 'state', 'zip', 'phone', 'email']);
});

it('snapshots the stored company details onto a generated invoice', function () {
    app(CompanySettings::class)->updateInvoicer([
        'name' => 'Quality Cleaning Plus LLC',
        'address' => '100 Main St',
        'city' => 'Dallas',
        'state' => 'TX',
        'zip' => '75201',
        'phone' => '214-555-0100',
        'email' => 'billing@qcp.test',
    ]);
    Setting::flushMemo();

    $property = Property::factory()->create();
    $period = PayrollPeriod::factory()->create(['property_id' => $property->id]);
    $timesheet = Timesheet::factory()->create([
        'property_id' => $property->id,
        'payroll_period_id' => $period->id,
        'status' => TimesheetStatus::Approved,
    ]);

    $invoice = app(GenerateInvoice::class)->handle($timesheet);

    expect($invoice->invoicer_snapshot['name'])->toBe('Quality Cleaning Plus LLC')
        ->and($invoice->invoicer_snapshot['city'])->toBe('Dallas')
        ->and($invoice->invoicer_snapshot['email'])->toBe('billing@qcp.test');

    // Editing afterwards must not reach an invoice already issued (ADR-0006).
    app(CompanySettings::class)->updateInvoicer(['name' => 'Renamed Later']);
    Setting::flushMemo();

    expect($invoice->refresh()->invoicer_snapshot['name'])->toBe('Quality Cleaning Plus LLC');
});

it('lets the ownership tier edit company details', function () {
    $this->seed(CompanySettingsSeeder::class);

    $this->actingAs(person('admin'))
        ->get(main('/admin/settings/company'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/settings/company')->has('invoicer.name'));

    $this->actingAs(person('admin'))
        ->patch(main('/admin/settings/company'), [
            'name' => 'Quality Cleaning Plus LLC',
            'city' => 'Dallas',
        ])
        ->assertSessionHasNoErrors();

    Setting::flushMemo();
    expect(app(CompanySettings::class)->invoicer()['name'])->toBe('Quality Cleaning Plus LLC');
});

it('keeps company details away from roles that cannot manage them', function () {
    foreach (['office_manager', 'hr', 'recruiter', 'payroll'] as $role) {
        $this->actingAs(person($role))
            ->get(main('/admin/settings/company'))
            ->assertForbidden();
    }
});

it('requires a company name', function () {
    $this->actingAs(person('admin'))
        ->patch(main('/admin/settings/company'), ['name' => ''])
        ->assertSessionHasErrors('name');
});
