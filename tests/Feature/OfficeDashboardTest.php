<?php

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Dashboards\Services\OfficeHome;
use App\Domain\Imports\Enums\ImportBatchStatus;
use App\Domain\Imports\Models\ImportBatch;
use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Models\Item;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\Marketing\Models\ContactInquiry;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Enums\PropertyTimeSource;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    // Monday Oct 12, 9 am in Chicago.
    $this->travelTo(CarbonImmutable::parse('2026-10-12 14:00:00', 'UTC'));
});

function office(): array
{
    return app(OfficeHome::class)->build(person('office_manager'));
}

it('gives the office manager their own dashboard', function () {
    $this->actingAs(person('office_manager'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/dashboard/office')->has('home.pipeline'));
});

it('counts weeks not submitted and lists invoices ready to send with each property\'s billing email', function () {
    $bayview = Property::factory()->create(['name' => 'Bayview Inn', 'billing_email' => 'ap@bayview.example']);
    $cedar = Property::factory()->create(['name' => 'Cedar Lodge', 'billing_email' => null]);

    $period = PayrollPeriod::factory()->create(['property_id' => $cedar->id, 'week_start' => '2026-09-28', 'week_end' => '2026-10-04']);
    Timesheet::factory()->create(['property_id' => $cedar->id, 'payroll_period_id' => $period->id, 'status' => TimesheetStatus::Draft]);
    TimeSummary::factory()->create(['property_id' => $cedar->id, 'payroll_period_id' => $period->id, 'week_start' => '2026-09-28', 'regular_minutes' => 600]);

    Invoice::factory()->create(['property_id' => $bayview->id, 'status' => InvoiceStatus::Invoiced, 'issue_date' => '2026-10-08', 'total' => 5210_40]);
    Invoice::factory()->create(['property_id' => $cedar->id, 'status' => InvoiceStatus::Invoiced, 'issue_date' => '2026-10-09', 'total' => 3870_15]);
    Invoice::factory()->create(['property_id' => $bayview->id, 'status' => InvoiceStatus::InvoiceSent, 'notification_sent_at' => now()->subHour()]);

    $pipeline = office()['pipeline'];

    expect($pipeline)->toMatchArray(['notSubmitted' => 1, 'notSubmittedWeeks' => 1, 'ready' => 2, 'readyAmount' => 9080.55, 'sent' => 1])
        ->and(collect($pipeline['invoices'])->pluck('email')->all())->toBe(['ap@bayview.example', null]);
});

it('sends an invoice from the dashboard to the address it shows', function () {
    $property = Property::factory()->create(['billing_email' => 'ap@bayview.example']);
    $invoice = Invoice::factory()->create(['property_id' => $property->id, 'status' => InvoiceStatus::Invoiced]);

    $this->actingAs(person('office_manager'))
        ->post(main("/admin/invoices/{$invoice->id}/send"), ['recipient' => office()['pipeline']['invoices'][0]['email']])
        ->assertRedirect();

    expect($invoice->fresh()->notification_recipient)->toBe('ap@bayview.example');
});

it('lists punches open for more than twelve hours, oldest first, with the property\'s recruiter', function () {
    $acme = Property::factory()->create(['name' => 'Acme Hotel', 'timezone' => 'America/Chicago']);
    $acme->assignments()->create(['person_id' => tap(person('recruiter'))->update(['name' => 'Rita Recruiter'])->id, 'role' => PropertyAssignmentRole::Recruiter->value]);

    TimeEntry::factory()->create(['property_id' => $acme->id, 'start_at_utc' => '2026-10-10 12:02:00', 'end_at_utc' => null, 'duration_minutes' => null, 'timezone' => 'America/Chicago']);
    TimeEntry::factory()->create(['property_id' => $acme->id, 'start_at_utc' => now()->subHours(3), 'end_at_utc' => null, 'duration_minutes' => null]); // a shift still going

    $punches = office()['punches'];

    expect($punches['total'])->toBe(1)
        ->and($punches['rows'][0])->toMatchArray(['property' => 'Acme Hotel', 'clockedIn' => 'Sat, Oct 10 · 7:02 AM', 'recruiter' => 'Rita Recruiter']);
});

it("shows which import-only properties haven't sent last week's file", function () {
    [$grand, $metro] = [
        Property::factory()->create(['name' => 'Grand Plaza', 'time_source' => PropertyTimeSource::Import]),
        Property::factory()->create(['name' => 'Metro Arena', 'time_source' => PropertyTimeSource::Import]),
    ];
    Property::factory()->create(['time_source' => PropertyTimeSource::ClockIn]);

    foreach ([$grand, $metro] as $property) {
        $period = PayrollPeriod::factory()->create(['property_id' => $property->id, 'week_start' => '2026-10-05', 'week_end' => '2026-10-11']);
        if ($property->is($metro)) {
            ImportBatch::factory()->create(['property_id' => $metro->id, 'payroll_period_id' => $period->id, 'status' => ImportBatchStatus::Applied, 'applied_at' => '2026-10-09 15:00:00']);
        }
    }

    $imports = office()['imports'];

    expect($imports)->toMatchArray(['total' => 2, 'in' => 1])
        ->and($imports['rows'][0])->toMatchArray(['name' => 'Grand Plaza', 'in' => false, 'detail' => 'No file for Oct 5 – 11 yet'])
        ->and($imports['rows'][0]['action'])->toBe(['label' => 'Upload', 'href' => "/admin/imports/create?property_id={$grand->id}"])
        ->and($imports['rows'][1])->toMatchArray(['name' => 'Metro Arena', 'in' => true, 'detail' => 'Imported Fri, Oct 9']);
});

it('lists supply requests, low stock and new inquiries', function () {
    SupplyRequest::factory()->count(2)->create();

    $polo = Item::factory()->create(['name' => 'Polo shirt']);
    ItemVariant::factory()->create(['item_id' => $polo->id, 'size' => 'M', 'current_stock' => 3, 'reorder_threshold' => 10]);
    ItemVariant::factory()->create(['current_stock' => 50, 'reorder_threshold' => 10]);
    PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Ordered]);

    ContactInquiry::factory()->create(['type' => 'business', 'company' => 'Northgate Hotel', 'inquiry_type' => 'Housekeeping staff']);
    ContactInquiry::factory()->count(3)->create(['type' => 'job_seeker']);
    ContactInquiry::factory()->create(['type' => 'job_seeker', 'handled_at' => now()]);

    $home = office();

    expect($home['supplies']['total'])->toBe(2)
        ->and($home['stock']['rows'])->toHaveCount(1)
        ->and($home['stock']['rows'][0])->toMatchArray(['name' => 'Polo shirt · M', 'detail' => '3 left · reorder at 10', 'urgent' => true])
        ->and($home['stock']['onOrder'])->toBe(1)
        ->and($home['inquiries']['total'])->toBe(4)
        ->and(collect($home['inquiries']['rows'])->pluck('title')->all())->toBe(['Northgate Hotel', '3 new job seekers']);
});
