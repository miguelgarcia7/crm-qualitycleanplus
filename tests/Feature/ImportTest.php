<?php

use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Imports\Actions\CreateImportBatch;
use App\Domain\Imports\Enums\ImportBatchStatus;
use App\Domain\Imports\Enums\ImportRowStatus;
use App\Domain\Imports\Models\ImportBatch;
use App\Domain\Imports\Support\HourImportTemplate;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Models\PersonExternalId;
use App\Domain\PropertyBible\Enums\HolidayType;
use App\Domain\PropertyBible\Enums\PropertyTimeSource;
use App\Domain\PropertyBible\Models\Holiday;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

function makeBatchFrom(array $s, string $path, ?Person $user = null): ImportBatch
{
    return app(CreateImportBatch::class)->handle($s['property'], $s['period'], $path, $user ?? person('office_manager'));
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

it('commits into the week’s draft timesheet that payroll:ensure-periods already made', function () {
    $s = importScenario();
    linkedContractor($s['property'], $s['position'], '1001');
    $draft = $s['period']->timesheet()->create(['property_id' => $s['property']->id, 'source' => 'clock_in', 'status' => TimesheetStatus::Draft]);
    $batch = makeBatch($s, [['Jane', '1001', 40, 20, 30, '2026-06-01', '2026-06-07', 'Housekeeper']]);

    $this->actingAs(person('office_manager'))->post(main("/admin/imports/{$batch->id}/commit"))->assertSessionHasNoErrors();

    $draft->refresh();
    expect(Timesheet::query()->where('payroll_period_id', $s['period']->id)->count())->toBe(1)
        ->and($draft->status)->toBe(TimesheetStatus::Invoiced)
        ->and($draft->source)->toBe('imported')
        ->and($draft->invoice_id)->toBe($batch->fresh()->invoice_id);
});

it('never pays a weekly total as holiday hours, even when the week starts on a holiday', function () {
    $s = importScenario();
    $holiday = Holiday::create(['name' => 'Week-start holiday', 'slug' => 'week-start-holiday', 'type' => HolidayType::Custom, 'month' => 6, 'day' => 1]);
    $s['property']->holidays()->attach($holiday->id);
    $person = linkedContractor($s['property'], $s['position'], '1001');
    $batch = makeBatch($s, [['Jane', '1001', 45, 20, 30, '2026-06-01', '2026-06-07', 'Housekeeper']]);

    $this->actingAs(person('office_manager'))->post(main("/admin/imports/{$batch->id}/commit"))->assertSessionHasNoErrors();

    $summary = TimeSummary::query()->where('person_id', $person->id)->firstOrFail();
    expect($summary->holiday_minutes)->toBe(0)
        ->and($summary->regular_minutes)->toBe(2400)
        ->and($summary->overtime_minutes)->toBe(300);
});

it('downloads the week’s file filled in with everyone working there, holidays marked', function () {
    $s = importScenario(); // week of Mon 2026-06-01
    $holiday = Holiday::create(['name' => 'Founders Day', 'slug' => 'founders-day', 'type' => HolidayType::Custom, 'month' => 6, 'day' => 4]);
    $s['property']->holidays()->attach($holiday->id);
    $jane = linkedContractor($s['property'], $s['position'], 'LS-1001', payRate: 1550);
    $jane->update(['name' => 'Jane Doe']);
    WorkOrder::query()->where('person_id', $jane->id)->update(['start_date' => '2026-05-01']);
    // Not working there that week: started after it.
    linkedContractor($s['property'], $s['position'], 'LS-1002')->workOrders()->update(['start_date' => '2026-06-15']);

    $response = $this->actingAs(person('office_manager'))
        ->get(main("/admin/imports/template?property_id={$s['property']->id}&payroll_period_id={$s['period']->id}"))
        ->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('Jun 1.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $book = IOFactory::load($path);
    $hours = $book->getSheetByName('Hours');

    expect($book->getSheetNames())->toBe(['Hours', 'How to fill this in'])
        ->and($hours->rangeToArray('A4:M4')[0])->toBe(['Name', 'Hotel ID', 'Position', 'Pay Rate', 'Mon 6/1', 'Tue 6/2', 'Wed 6/3', 'Thu 6/4 Holiday', 'Fri 6/5', 'Sat 6/6', 'Sun 6/7', 'Total', 'Notes'])
        ->and($hours->rangeToArray('A5:D5', null, false, false)[0])->toBe(['Jane Doe', 'LS-1001', 'Housekeeper', 15.5])
        ->and($hours->rangeToArray('A6:B6', null, false, false)[0])->toBe([null, null])
        ->and($hours->getCell('L5')->getValue())->toBe('=IF(COUNT(E5:K5)=0,"",SUM(E5:K5))')
        ->and($book->getSheetByName('How to fill this in')->getCell('B14')->getValue())->toContain('Founders Day (Thu, Jun 4)');
});

it('keeps the file download to the user’s own import hotels', function () {
    $s = importScenario();
    $url = main("/admin/imports/template?property_id={$s['property']->id}&payroll_period_id={$s['period']->id}");

    $this->actingAs(person('recruiter'))->get($url)->assertForbidden(); // not assigned
    $this->actingAs(person('hr'))->get($url)->assertForbidden();         // no imports.upload

    $clockIn = Property::factory()->create(['time_source' => PropertyTimeSource::ClockIn]);
    $period = PayrollPeriod::factory()->create(['property_id' => $clockIn->id]);
    $this->actingAs(person('office_manager'))
        ->get(main("/admin/imports/template?property_id={$clockIn->id}&payroll_period_id={$period->id}"))
        ->assertStatus(422);
});

/** The week's downloaded file, with hours typed in per hotel ID (Mon first). */
function filledTemplate(array $s, array $hoursById, ?PayrollPeriod $period = null): string
{
    $book = app(HourImportTemplate::class)->build($s['property'], $period ?? $s['period']);
    $sheet = $book->getSheetByName('Hours');
    for ($row = HourImportTemplate::HEADER_ROW + 1; $row <= $sheet->getHighestRow(); $row++) {
        foreach ($hoursById[(string) $sheet->getCell("B{$row}")->getValue()] ?? [] as $i => $hours) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex(5 + $i).$row, $hours);
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    (new Xlsx($book))->save($path);

    return $path;
}

it('imports our downloaded file day by day: holiday on its day, overtime past 40', function () {
    $s = importScenario(); // week of Mon 2026-06-01
    $holiday = Holiday::create(['name' => 'Founders Day', 'slug' => 'founders-day', 'type' => HolidayType::Custom, 'month' => 6, 'day' => 4]);
    $s['property']->holidays()->attach($holiday->id);
    $jane = tap(linkedContractor($s['property'], $s['position'], 'LS-1001'))->update(['name' => 'Alice Able']);
    $idle = tap(linkedContractor($s['property'], $s['position'], 'LS-1002'))->update(['name' => 'Zed Zee']);
    WorkOrder::query()->update(['start_date' => '2026-05-01']);

    // Mon–Wed 10 h, Thu (holiday) 8 h, Fri 6 h = 44 h; the second person didn't work.
    $path = filledTemplate($s, ['LS-1001' => [10, 10, 10, 8, 6]]);
    $manager = person('office_manager');
    $this->actingAs($manager)->post(main('/admin/imports'), [
        'property_id' => $s['property']->id, 'payroll_period_id' => $s['period']->id,
        'file' => new UploadedFile($path, 'Lakeside.xlsx', null, null, true),
    ])->assertSessionHasNoErrors();
    $batch = ImportBatch::query()->latest('id')->firstOrFail();

    $this->actingAs($manager)->get(main("/admin/imports/{$batch->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('week.3', ['label' => 'Thu 6/4', 'holiday' => 'Founders Day'])
            ->where('rows.0.days', [10, 10, 10, 8, 6, null, null])
            ->where('rows.0.status', 'matched')
            ->where('rows.0.regular_hours', 32)
            ->where('rows.0.overtime_hours', 4)
            ->where('rows.0.holiday_hours', 8)
            ->where('rows.1.status', 'skipped')
            ->where('rows.1.status_label', 'No hours this week'));

    $this->actingAs($manager)->post(main("/admin/imports/{$batch->id}/commit"))->assertSessionHasNoErrors();

    $entries = TimeEntry::query()->where('person_id', $jane->id)->orderBy('work_date')->get();
    $summary = TimeSummary::query()->where('person_id', $jane->id)->firstOrFail();
    expect($entries->map(fn (TimeEntry $e) => [$e->work_date?->toDateString(), $e->duration_minutes])->all())
        ->toBe([['2026-06-01', 600], ['2026-06-02', 600], ['2026-06-03', 600], ['2026-06-04', 480], ['2026-06-05', 360]])
        ->and($entries->every(fn (TimeEntry $e) => $e->start_at_utc === null && $e->end_at_utc === null))->toBeTrue()
        ->and([$summary->regular_minutes, $summary->overtime_minutes, $summary->holiday_minutes])->toBe([1920, 240, 480])
        ->and(TimeEntry::query()->where('person_id', $idle->id)->exists())->toBeFalse();
});

it('refuses our file for a different week than the one picked', function () {
    $s = importScenario();
    linkedContractor($s['property'], $s['position'], 'LS-1001');
    WorkOrder::query()->update(['start_date' => '2026-05-01']);
    $other = PayrollPeriod::factory()->create(['property_id' => $s['property']->id, 'week_start' => '2026-06-08', 'week_end' => '2026-06-14']);

    expect(fn () => app(CreateImportBatch::class)->handle($s['property'], $other, filledTemplate($s, ['LS-1001' => [8]]), person('office_manager')))
        ->toThrow(ValidationException::class, 'it has 6/1');
});

it('says which day has hours it can’t read', function () {
    $s = importScenario();
    linkedContractor($s['property'], $s['position'], 'LS-1001');
    WorkOrder::query()->update(['start_date' => '2026-05-01']);

    expect(fn () => makeBatchFrom($s, filledTemplate($s, ['LS-1001' => [8, '9-5']])))
        ->toThrow(ValidationException::class, 'Row 5, Tue: type the hours worked as a number');
});
