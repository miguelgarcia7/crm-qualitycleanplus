<?php

use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Imports\Actions\CreateImportBatch;
use App\Domain\Imports\Enums\ImportBatchStatus;
use App\Domain\Imports\Enums\ImportRowStatus;
use App\Domain\Imports\Models\ImportBatch;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Models\PersonExternalId;
use App\Domain\PropertyBible\Enums\PropertyTimeSource;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

const HEADERS = ['Name', 'EmployeeID', 'TotalHours', 'PayRate', 'BillRate', 'StartDate', 'EndDate', 'Position'];

/**
 * @param  list<array<int, mixed>>  $rows
 */
function xlsxPath(array $rows, array $headers = HEADERS): string
{
    $ss = new Spreadsheet;
    $ss->getActiveSheet()->fromArray(array_merge([$headers], $rows), null, 'A1');
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($ss))->save($path);

    return $path;
}

/**
 * Import-only property with a Housekeeper rate and an open period for week 2026-06-01.
 *
 * @return array{property: Property, position: Position, period: PayrollPeriod}
 */
function importScenario(): array
{
    $property = Property::factory()->create([
        'time_source' => PropertyTimeSource::Import,
        'timezone' => 'America/Phoenix',
        'tax_rate' => 0,
    ]);
    $position = Position::factory()->create(['name' => 'Housekeeper']);
    $property->positionRates()->create([
        'position_id' => $position->id, 'effective_date' => '2026-05-01',
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500, 'is_active' => true,
    ]);
    $period = PayrollPeriod::factory()->create([
        'property_id' => $property->id, 'week_start' => '2026-06-01', 'week_end' => '2026-06-07',
        'status' => PayrollPeriodStatus::Open,
    ]);

    return compact('property', 'position', 'period');
}

function linkedContractor(Property $property, Position $position, string $externalId, int $payRate = 2000, int $billRate = 3000): Person
{
    $person = Person::factory()->create(['status' => PersonStatus::ContractorActive]);
    PersonExternalId::create(['person_id' => $person->id, 'property_id' => $property->id, 'external_id' => $externalId]);
    WorkOrder::factory()->create([
        'person_id' => $person->id, 'property_id' => $property->id, 'position_id' => $position->id,
        'pay_rate' => $payRate, 'bill_rate' => $billRate, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500,
        'status' => WorkOrderStatus::Active,
    ]);

    return $person;
}

function makeBatch(array $s, array $rows, ?Person $user = null): ImportBatch
{
    return app(CreateImportBatch::class)->handle(
        $s['property'], $s['period'], xlsxPath($rows), $user ?? person('office_manager'),
    );
}

it('parses and classifies rows as matched, rate-conflict, and unmatched', function () {
    $s = importScenario();
    linkedContractor($s['property'], $s['position'], '1001', payRate: 2000);          // matches file
    linkedContractor($s['property'], $s['position'], '1002', payRate: 2500);          // conflicts with file pay 20

    $batch = makeBatch($s, [
        ['Jane Matched', '1001', 40, 20, 30, '2026-06-01', '2026-06-07', 'Housekeeper'],
        ['Carl Conflict', '1002', 40, 20, 30, '2026-06-01', '2026-06-07', 'Housekeeper'],
        ['Nora New', '9999', 40, 20, 30, '2026-06-01', '2026-06-07', 'Housekeeper'],
    ]);

    expect($batch->status)->toBe(ImportBatchStatus::Preview)
        ->and($batch->rows()->where('status', ImportRowStatus::Matched->value)->count())->toBe(1)
        ->and($batch->rows()->where('status', ImportRowStatus::RateConflict->value)->count())->toBe(1)
        ->and($batch->rows()->where('status', ImportRowStatus::Unmatched->value)->count())->toBe(1);
});

it('commits a matched import into an approved timesheet and frozen invoice', function () {
    $s = importScenario();
    linkedContractor($s['property'], $s['position'], '1001');
    $batch = makeBatch($s, [['Jane', '1001', 40, 20, 30, '2026-06-01', '2026-06-07', 'Housekeeper']]);

    $this->actingAs(person('office_manager'))
        ->post(main("/admin/imports/{$batch->id}/commit"))
        ->assertRedirect();

    $invoice = Invoice::query()->latest('id')->firstOrFail();
    $entry = TimeEntry::query()->latest('id')->firstOrFail();

    expect($batch->fresh()->status)->toBe(ImportBatchStatus::Applied)
        ->and($invoice->status)->toBe(InvoiceStatus::Invoiced)
        ->and($invoice->total)->toBe(120000)                       // 40h × $30 bill, tax 0
        ->and($invoice->timesheet_id)->not->toBeNull()
        ->and($entry->source->value)->toBe('imported')
        ->and($entry->duration_minutes)->toBe(2400)
        ->and($entry->start_at_utc)->toBeNull()
        ->and($s['period']->fresh()->status)->toBe(PayrollPeriodStatus::Invoiced);
});

it('rejects a duplicate external id in the file', function () {
    $s = importScenario();

    expect(fn () => makeBatch($s, [
        ['A', '1001', 40, 20, 30, '2026-06-01', '2026-06-07', 'Housekeeper'],
        ['B', '1001', 30, 20, 30, '2026-06-01', '2026-06-07', 'Housekeeper'],
    ]))->toThrow(ValidationException::class);
});

it('blocks a zero pay rate', function () {
    $s = importScenario();

    expect(fn () => makeBatch($s, [['A', '1001', 40, 0, 30, '2026-06-01', '2026-06-07', 'Housekeeper']]))
        ->toThrow(ValidationException::class);
});

it('blocks a file whose week does not match the selected period', function () {
    $s = importScenario();

    expect(fn () => makeBatch($s, [['A', '1001', 40, 20, 30, '2026-06-08', '2026-06-14', 'Housekeeper']]))
        ->toThrow(ValidationException::class);
});

it('creates a contractor and external id when resolving an unmatched row, then commits', function () {
    $s = importScenario();
    $batch = makeBatch($s, [['Nora New', '9999', 40, 20, 30, '2026-06-01', '2026-06-07', 'Housekeeper']]);
    $row = $batch->rows()->firstOrFail();

    $this->actingAs(person('office_manager'))
        ->post(main("/admin/imports/{$batch->id}/resolve"), ['row_id' => $row->id, 'action' => 'create_contractor', 'name' => 'Nora New'])
        ->assertRedirect();

    $link = PersonExternalId::query()->where('property_id', $s['property']->id)->where('external_id', '9999')->firstOrFail();
    expect($link->person->name)->toBe('Nora New')
        ->and($row->fresh()->status)->toBe(ImportRowStatus::NeedsWoCreation);

    $this->actingAs(person('office_manager'))->post(main("/admin/imports/{$batch->id}/commit"))->assertRedirect();

    $wo = WorkOrder::query()->where('person_id', $link->person_id)->firstOrFail();
    expect($wo->source->value)->toBe('imported')->and($wo->pay_rate)->toBe(2000);
});

it('uses the file rate and closes the old work order on a rate conflict', function () {
    $s = importScenario();
    $person = linkedContractor($s['property'], $s['position'], '1002', payRate: 2000);
    $oldWo = $person->workOrders()->firstOrFail();
    $batch = makeBatch($s, [['Carl', '1002', 40, 25, 35, '2026-06-01', '2026-06-07', 'Housekeeper']]);
    $row = $batch->rows()->firstOrFail();
    expect($row->status)->toBe(ImportRowStatus::RateConflict);

    $this->actingAs(person('office_manager'))
        ->post(main("/admin/imports/{$batch->id}/resolve"), ['row_id' => $row->id, 'action' => 'use_file'])
        ->assertRedirect();
    $this->actingAs(person('office_manager'))->post(main("/admin/imports/{$batch->id}/commit"))->assertRedirect();

    $newWo = WorkOrder::query()->where('person_id', $person->id)->where('pay_rate', 2500)->firstOrFail();
    expect($oldWo->fresh()->status)->toBe(WorkOrderStatus::Closed)
        ->and($newWo->status)->toBe(WorkOrderStatus::Active)
        ->and($newWo->source->value)->toBe('imported');
});

it('applies a staged billable adjustment to the invoice', function () {
    $s = importScenario();
    linkedContractor($s['property'], $s['position'], '1001');
    $batch = makeBatch($s, [['Jane', '1001', 40, 20, 30, '2026-06-01', '2026-06-07', 'Housekeeper']]);
    $row = $batch->rows()->firstOrFail();

    $this->actingAs(person('office_manager'))->post(main("/admin/imports/{$batch->id}/adjustments"), [
        'adjustments' => [['row_id' => $row->id, 'value' => 50, 'type' => 'incentive', 'is_billable' => true, 'notes' => 'Bonus']],
    ])->assertRedirect();

    $this->actingAs(person('office_manager'))->post(main("/admin/imports/{$batch->id}/commit"))->assertRedirect();

    $invoice = Invoice::query()->latest('id')->firstOrFail();
    expect(TimeEntryAdjustment::query()->where('is_billable', true)->sum('value'))->toBe(5000)
        ->and($invoice->adjustment_total)->toBe(5000)
        ->and($invoice->total)->toBe(125000);            // 120000 work + 5000 incentive
});

it('rolls back a committed import: invoice voided, entries removed, period reopened', function () {
    $s = importScenario();
    linkedContractor($s['property'], $s['position'], '1001');
    $batch = makeBatch($s, [['Jane', '1001', 40, 20, 30, '2026-06-01', '2026-06-07', 'Housekeeper']]);
    $this->actingAs(person('office_manager'))->post(main("/admin/imports/{$batch->id}/commit"))->assertRedirect();

    $invoice = Invoice::query()->latest('id')->firstOrFail();

    $this->actingAs(person('office_manager'))->post(main("/admin/imports/{$batch->id}/rollback"))->assertRedirect();

    expect($batch->fresh()->status)->toBe(ImportBatchStatus::RolledBack)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Voided)
        ->and($invoice->fresh()->voided_at)->not->toBeNull()
        ->and(TimeEntry::query()->count())->toBe(0)                    // soft-deleted
        ->and(TimeEntry::withTrashed()->count())->toBe(1)
        ->and($s['period']->fresh()->status)->toBe(PayrollPeriodStatus::Open);
});

it('void-and-re-import redirects to a prefilled new import', function () {
    $s = importScenario();
    linkedContractor($s['property'], $s['position'], '1001');
    $batch = makeBatch($s, [['Jane', '1001', 40, 20, 30, '2026-06-01', '2026-06-07', 'Housekeeper']]);
    $this->actingAs(person('office_manager'))->post(main("/admin/imports/{$batch->id}/commit"))->assertRedirect();

    $this->actingAs(person('office_manager'))
        ->post(main("/admin/imports/{$batch->id}/rollback"), ['reimport' => true])
        ->assertRedirect(main('/admin/imports/create')."?property_id={$s['property']->id}&payroll_period_id={$s['period']->id}");
});

it('forbids a recruiter from rolling back an import', function () {
    $s = importScenario();
    linkedContractor($s['property'], $s['position'], '1001');
    $batch = makeBatch($s, [['Jane', '1001', 40, 20, 30, '2026-06-01', '2026-06-07', 'Housekeeper']]);
    $this->actingAs(person('office_manager'))->post(main("/admin/imports/{$batch->id}/commit"))->assertRedirect();

    $this->actingAs(person('recruiter'))->post(main("/admin/imports/{$batch->id}/rollback"))->assertForbidden();
});

it('only offers import-only properties on the upload form', function () {
    $import = Property::factory()->create(['time_source' => PropertyTimeSource::Import, 'name' => 'Import Hotel']);
    Property::factory()->create(['time_source' => PropertyTimeSource::ClockIn, 'name' => 'Clock Hotel']);

    $this->actingAs(person('office_manager'))
        ->get(main('/admin/imports/create'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/imports/create')
            ->where('properties', fn ($properties) => collect($properties)->pluck('id')->all() === [$import->id]),
        );
});
