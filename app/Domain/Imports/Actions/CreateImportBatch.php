<?php

namespace App\Domain\Imports\Actions;

use App\Domain\Imports\Enums\ImportBatchStatus;
use App\Domain\Imports\Models\ImportBatch;
use App\Domain\Imports\Models\ImportBatchRow;
use App\Domain\Imports\Services\HourImportParser;
use App\Domain\Imports\Support\ImportRowMatcher;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Parses an uploaded hour file into a preview batch (40-flows/import-hours.md §Step 1):
 * dedupes the file, validates it covers the selected period's week, then matches each
 * row to a contractor (people_external_ids) and an active work order, classifying it
 * matched / rate_conflict / needs_wo_creation / unmatched. Leaves the batch in
 * `preview` for the resolve + commit steps.
 */
class CreateImportBatch
{
    public function __construct(
        private readonly HourImportParser $parser,
        private readonly ImportRowMatcher $matcher,
    ) {}

    public function handle(Property $property, PayrollPeriod $period, mixed $file, Person $user): ImportBatch
    {
        $hash = hash_file('sha256', is_string($file) ? $file : $file->getRealPath());

        $duplicate = ImportBatch::query()
            ->where('property_id', $property->id)
            ->where('file_hash', $hash)
            ->where('status', ImportBatchStatus::Applied->value)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'file' => 'This exact file has already been imported for this property. Use "Void and Re-import" if you need to replace it.',
            ]);
        }

        $rows = $this->parser->parse($file);

        $this->assertCoversPeriodWeek($rows, $period, $property->timezone);

        return DB::transaction(function () use ($property, $period, $user, $file, $hash, $rows): ImportBatch {
            $batch = ImportBatch::create([
                'property_id' => $property->id,
                'payroll_period_id' => $period->id,
                'file_name' => is_string($file) ? basename($file) : $file->getClientOriginalName(),
                'file_hash' => $hash,
                'status' => ImportBatchStatus::Parsing,
                'uploaded_by' => $user->id,
            ]);

            foreach ($rows as $row) {
                $this->createRow($batch->id, $property, $row);
            }

            $batch->update(['status' => ImportBatchStatus::Preview]);

            return $batch;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createRow(int $batchId, Property $property, array $row): void
    {
        $person = $this->matcher->matchPerson($property->id, (string) $row['external_id']);
        $payCents = (int) round(((float) $row['pay_rate']) * 100);
        $billCents = $row['bill_rate'] === null ? null : (int) round(((float) $row['bill_rate']) * 100);

        $classification = $this->matcher->classify($person, $property->id, $payCents, $billCents);
        $existingWo = $classification['existing'];

        $rawData = array_merge($row, [
            'pay_rate_cents' => $payCents,
            'bill_rate_cents' => $billCents,
            'existing_wo' => $existingWo === null ? null : [
                'id' => $existingWo->id,
                'pay_rate' => $existingWo->pay_rate,
                'bill_rate' => $existingWo->bill_rate,
                'ot_pay_rate' => $existingWo->ot_pay_rate,
                'ot_bill_rate' => $existingWo->ot_bill_rate,
            ],
            'rate_delta_warning' => $classification['delta_warning'],
        ]);

        ImportBatchRow::create([
            'import_batch_id' => $batchId,
            'row_number' => (int) $row['row_number'],
            'raw_data' => $rawData,
            'matched_person_id' => $person?->id,
            'existing_wo_id' => $existingWo?->id,
            'status' => $classification['status'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function assertCoversPeriodWeek(array $rows, PayrollPeriod $period, string $timezone): void
    {
        $periodWeek = $period->week_start->toDateString();

        foreach ($rows as $row) {
            $rowWeek = CarbonImmutable::parse((string) $row['start_date'], $timezone)
                ->startOfWeek(CarbonImmutable::MONDAY)
                ->toDateString();

            if ($rowWeek !== $periodWeek) {
                throw ValidationException::withMessages([
                    'file' => "Row {$row['row_number']} is for the week of {$rowWeek}, but the selected period is the week of {$periodWeek}. Pick the matching period or file.",
                ]);
            }
        }
    }
}
