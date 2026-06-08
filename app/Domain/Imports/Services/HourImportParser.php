<?php

namespace App\Domain\Imports\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Reads a weekly hour-export .xlsx into normalized rows (40-flows/import-hours.md).
 * Headers are detected flexibly (order may vary; common name variants accepted) and
 * the file is hard-rejected for the spec's structural problems: missing header row,
 * a missing required column, a zero/negative pay rate, or a duplicate external id.
 *
 * Each returned row: row_number (spreadsheet line), name, external_id, hours,
 * pay_rate, bill_rate|null, start_date (Y-m-d), end_date (Y-m-d), position|null.
 */
class HourImportParser
{
    /**
     * Canonical field => accepted header spellings (normalized: lowercased,
     * non-alphanumerics stripped). The first six are required.
     *
     * @var array<string, list<string>>
     */
    private const HEADERS = [
        'name' => ['name', 'employeename', 'contractor', 'contractorname'],
        'external_id' => ['id', 'employeeid', 'empid', 'externalid', 'employeenumber'],
        'hours' => ['totalhours', 'hours', 'hrs'],
        'pay_rate' => ['payrate', 'rate'],
        'start_date' => ['startdate', 'weekstart', 'periodstart'],
        'end_date' => ['enddate', 'weekend', 'periodend'],
        'bill_rate' => ['billrate', 'chargerate'],
        'position' => ['position', 'role', 'jobtitle'],
    ];

    private const REQUIRED = ['name', 'external_id', 'hours', 'pay_rate', 'start_date', 'end_date'];

    /**
     * @return list<array<string, mixed>>
     */
    public function parse(UploadedFile|string $file): array
    {
        $sheets = Excel::toArray(new class {}, $file);
        $sheet = $sheets[0] ?? [];

        // First non-empty line is the header row.
        $headerIndex = null;
        foreach ($sheet as $i => $line) {
            if ($this->rowHasContent($line)) {
                $headerIndex = $i;
                break;
            }
        }

        if ($headerIndex === null) {
            throw ValidationException::withMessages(['file' => 'The file appears to be empty — no header row found.']);
        }

        $map = $this->mapHeaders($sheet[$headerIndex]);

        $missing = array_diff(self::REQUIRED, array_keys($map));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'Missing required column(s): '.implode(', ', $missing).'.',
            ]);
        }

        $rows = [];
        $seenIds = [];
        foreach ($sheet as $i => $line) {
            if ($i <= $headerIndex || ! $this->rowHasContent($line)) {
                continue;
            }

            $rowNumber = $i + 1; // 1-based spreadsheet line
            $row = $this->normalizeRow($line, $map, $rowNumber);

            if (in_array($row['external_id'], $seenIds, true)) {
                throw ValidationException::withMessages([
                    'file' => "Duplicate ID \"{$row['external_id']}\" in the file (row {$rowNumber}). Clean the file so each contractor appears once.",
                ]);
            }
            $seenIds[] = $row['external_id'];

            $rows[] = $row;
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The file has a header but no data rows.']);
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $headerLine
     * @return array<string, int> canonical field => column index
     */
    private function mapHeaders(array $headerLine): array
    {
        $map = [];
        foreach ($headerLine as $col => $value) {
            $normalized = $this->normalizeHeader((string) $value);
            if ($normalized === '') {
                continue;
            }
            foreach (self::HEADERS as $field => $variants) {
                if (! isset($map[$field]) && in_array($normalized, $variants, true)) {
                    $map[$field] = $col;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $line
     * @param  array<string, int>  $map
     * @return array<string, mixed>
     */
    private function normalizeRow(array $line, array $map, int $rowNumber): array
    {
        $value = fn (string $field) => isset($map[$field]) ? ($line[$map[$field]] ?? null) : null;

        $externalId = trim((string) $value('external_id'));
        if ($externalId === '') {
            throw ValidationException::withMessages(['file' => "Missing ID on row {$rowNumber}."]);
        }

        $payRate = (float) $value('pay_rate');
        if ($payRate <= 0) {
            throw ValidationException::withMessages([
                'file' => "Pay rate must be greater than zero (row {$rowNumber}).",
            ]);
        }

        $hours = (float) $value('hours');
        if ($hours <= 0) {
            throw ValidationException::withMessages([
                'file' => "Hours must be greater than zero (row {$rowNumber}).",
            ]);
        }

        $billRaw = $value('bill_rate');
        $position = $value('position');

        return [
            'row_number' => $rowNumber,
            'name' => trim((string) $value('name')),
            'external_id' => $externalId,
            'hours' => $hours,
            'pay_rate' => $payRate,
            'bill_rate' => ($billRaw === null || $billRaw === '') ? null : (float) $billRaw,
            'start_date' => $this->normalizeDate($value('start_date'), $rowNumber, 'start date'),
            'end_date' => $this->normalizeDate($value('end_date'), $rowNumber, 'end date'),
            'position' => ($position === null || trim((string) $position) === '') ? null : trim((string) $position),
        ];
    }

    private function normalizeHeader(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower(trim($value)));
    }

    /**
     * @param  array<int, mixed>  $line
     */
    private function rowHasContent(array $line): bool
    {
        foreach ($line as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return true;
            }
        }

        return false;
    }

    private function normalizeDate(mixed $value, int $rowNumber, string $label): string
    {
        if ($value === null || $value === '') {
            throw ValidationException::withMessages(['file' => "Missing {$label} on row {$rowNumber}."]);
        }

        try {
            // Excel stores dates as serial numbers unless the cell is text.
            if (is_numeric($value)) {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            }

            return CarbonImmutable::parse((string) $value)->toDateString();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => "Could not read the {$label} on row {$rowNumber}.",
            ]);
        }
    }
}
