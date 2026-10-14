<?php

namespace App\Domain\Imports\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Reads an hour file into normalized rows (40-flows/import-hours.md). Two layouts:
 *
 * - **Daily** — our downloaded file (HourImportTemplate) or anything shaped like
 *   it: a column per day ("Mon 10/5", "Tue", "Wednesday"…), one row per person.
 *   The days are dated from the week being imported; a dated header for another
 *   week is refused. The pay rate may be blank (the work order's rate is used).
 * - **Weekly** — a hotel's payroll export: one row per person with the week's
 *   total hours, pay rate and the week's start and end dates.
 *
 * The header row is the first row naming the columns (titles and notes above it
 * are fine; order and common name variants don't matter). The file is refused
 * for the spec's structural problems: no header, a missing required column, a
 * bad pay rate or hours value, hours with no ID, or a duplicate ID. A person with
 * no hours that week comes back with 0 hours, to be skipped.
 *
 * Each returned row: row_number (spreadsheet line), name, external_id, hours,
 * pay_rate|null, bill_rate|null, start_date (Y-m-d), end_date (Y-m-d),
 * position|null, days (Y-m-d => hours, daily layout only) or null.
 */
class HourImportParser
{
    /**
     * Canonical field => accepted header spellings (normalized: lowercased,
     * non-alphanumerics stripped).
     *
     * @var array<string, list<string>>
     */
    private const HEADERS = [
        'name' => ['name', 'employeename', 'contractor', 'contractorname'],
        'external_id' => ['id', 'employeeid', 'empid', 'externalid', 'employeenumber', 'hotelid'],
        'hours' => ['totalhours', 'hours', 'hrs'],
        'pay_rate' => ['payrate', 'rate'],
        'start_date' => ['startdate', 'weekstart', 'periodstart'],
        'end_date' => ['enddate', 'weekend', 'periodend'],
        'bill_rate' => ['billrate', 'chargerate'],
        'position' => ['position', 'role', 'jobtitle'],
    ];

    private const REQUIRED_WEEKLY = ['name', 'external_id', 'hours', 'pay_rate', 'start_date', 'end_date'];

    private const REQUIRED_DAILY = ['name', 'external_id'];

    /** How far down to look for the header row. */
    private const HEADER_SEARCH_ROWS = 15;

    /**
     * @param  CarbonImmutable|null  $weekStart  the first day of the week being imported; dates a daily file
     * @return list<array<string, mixed>>
     */
    public function parse(UploadedFile|string $file, ?CarbonImmutable $weekStart = null): array
    {
        $sheets = Excel::toArray(new class {}, $file);
        $sheet = $sheets[0] ?? [];

        [$headerIndex, $map, $dayColumns] = $this->findHeader($sheet);

        $daily = $dayColumns !== [];
        $missing = array_diff($daily ? self::REQUIRED_DAILY : self::REQUIRED_WEEKLY, array_keys($map));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'Missing required column(s): '.implode(', ', $missing).'.',
            ]);
        }

        $days = $daily ? $this->dateDays($dayColumns, $weekStart) : [];

        $rows = [];
        $seenIds = [];
        foreach ($sheet as $i => $line) {
            if ($i <= $headerIndex) {
                continue;
            }

            $rowNumber = $i + 1; // 1-based spreadsheet line
            $row = $daily
                ? $this->dailyRow($line, $map, $days, $rowNumber, $weekStart)
                : $this->weeklyRow($line, $map, $rowNumber);
            if ($row === null) {
                continue;
            }

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
     * The header row: the first row (near the top) naming at least two known
     * columns or a day. Returns its index, field => column, and the day columns
     * (column => [ISO weekday, "n/j" date or null]).
     *
     * @param  array<int, array<int, mixed>>  $sheet
     * @return array{0: int, 1: array<string, int>, 2: array<int, array{0: int, 1: string|null}>}
     */
    private function findHeader(array $sheet): array
    {
        $seen = 0;
        foreach ($sheet as $i => $line) {
            if (! $this->rowHasContent($line)) {
                continue;
            }
            if (++$seen > self::HEADER_SEARCH_ROWS) {
                break;
            }

            [$map, $dayColumns] = $this->mapHeaders($line);
            if (count($map) >= 2 || ($dayColumns !== [] && $map !== [])) {
                return [$i, $map, $dayColumns];
            }
        }

        throw ValidationException::withMessages(['file' => 'Couldn\'t find the header row. The file needs a row naming its columns, like Name, ID and Hours.']);
    }

    /**
     * @param  array<int, mixed>  $headerLine
     * @return array{0: array<string, int>, 1: array<int, array{0: int, 1: string|null}>}
     */
    private function mapHeaders(array $headerLine): array
    {
        $map = [];
        $days = [];
        foreach ($headerLine as $col => $value) {
            $raw = trim((string) $value);
            $normalized = $this->normalizeHeader($raw);
            if ($normalized === '') {
                continue;
            }

            if (preg_match('/^(mon|tue|wed|thu|fri|sat|sun)[a-z]*\.?(?:\s+(\d{1,2}\/\d{1,2}))?/i', $raw, $m) === 1) {
                $iso = array_search(strtolower($m[1]), ['', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true);
                $days[$col] = [(int) $iso, $m[2] ?? null];

                continue;
            }

            foreach (self::HEADERS as $field => $variants) {
                if (! isset($map[$field]) && in_array($normalized, $variants, true)) {
                    $map[$field] = $col;
                }
            }
        }

        return [$map, $days];
    }

    /**
     * Each day column's date in the week being imported. A header that names a
     * date ("Mon 10/5") must be that week's.
     *
     * @param  array<int, array{0: int, 1: string|null}>  $dayColumns
     * @return array<int, string> column => Y-m-d
     */
    private function dateDays(array $dayColumns, ?CarbonImmutable $weekStart): array
    {
        if ($weekStart === null) {
            throw ValidationException::withMessages(['file' => 'Pick the week this file is for.']);
        }

        $week = [];
        for ($d = 0; $d < 7; $d++) {
            $date = $weekStart->addDays($d);
            $week[$date->isoWeekday()] = $date;
        }

        $dates = [];
        foreach ($dayColumns as $col => [$iso, $monthDay]) {
            $date = $week[$iso];
            if ($monthDay !== null && $monthDay !== $date->format('n/j')) {
                throw ValidationException::withMessages([
                    'file' => "This file's days don't match the week you picked ({$weekStart->format('M j')} – {$weekStart->addDays(6)->format('M j')}): it has {$monthDay}. Pick the week the file is for, or download that week's file.",
                ]);
            }
            $dates[$col] = $date->toDateString();
        }

        return $dates;
    }

    /**
     * A row of the daily layout, or null for a line that isn't a person (the
     * notes under the table, an empty row).
     *
     * @param  array<int, mixed>  $line
     * @param  array<string, int>  $map
     * @param  array<int, string>  $days  column => Y-m-d
     * @return array<string, mixed>|null
     */
    private function dailyRow(array $line, array $map, array $days, int $rowNumber, ?CarbonImmutable $weekStart): ?array
    {
        $value = fn (string $field) => isset($map[$field]) ? ($line[$map[$field]] ?? null) : null;
        $externalId = trim((string) $value('external_id'));

        $hoursByDay = [];
        foreach ($days as $col => $date) {
            $cell = $line[$col] ?? null;
            if ($cell === null || trim((string) $cell) === '') {
                continue;
            }
            if (! is_numeric($cell) || (float) $cell < 0 || (float) $cell > 24) {
                $day = CarbonImmutable::parse($date)->format('D');
                throw ValidationException::withMessages([
                    'file' => "Row {$rowNumber}, {$day}: type the hours worked as a number from 0 to 24, like 8 or 7.5.",
                ]);
            }
            if ((float) $cell > 0) {
                $hoursByDay[$date] = round((float) $cell, 2);
            }
        }

        if ($externalId === '') {
            if ($hoursByDay === []) {
                return null;
            }
            throw ValidationException::withMessages(['file' => "Row {$rowNumber} has hours but no hotel ID."]);
        }

        $position = $value('position');

        return [
            'row_number' => $rowNumber,
            'name' => trim((string) $value('name')),
            'external_id' => $externalId,
            'hours' => round(array_sum($hoursByDay), 2),
            'pay_rate' => $this->rate($value('pay_rate'), $rowNumber, required: false),
            'bill_rate' => $this->rate($value('bill_rate'), $rowNumber, required: false),
            'start_date' => $weekStart?->toDateString(),
            'end_date' => $weekStart?->addDays(6)->toDateString(),
            'position' => ($position === null || trim((string) $position) === '') ? null : trim((string) $position),
            'days' => $hoursByDay,
        ];
    }

    /**
     * A row of the weekly-total layout, or null for an empty line.
     *
     * @param  array<int, mixed>  $line
     * @param  array<string, int>  $map
     * @return array<string, mixed>|null
     */
    private function weeklyRow(array $line, array $map, int $rowNumber): ?array
    {
        if (! $this->rowHasContent($line)) {
            return null;
        }

        $value = fn (string $field) => isset($map[$field]) ? ($line[$map[$field]] ?? null) : null;

        $externalId = trim((string) $value('external_id'));
        if ($externalId === '') {
            throw ValidationException::withMessages(['file' => "Missing ID on row {$rowNumber}."]);
        }

        $hours = $value('hours');
        if ($hours !== null && trim((string) $hours) !== '' && (! is_numeric($hours) || (float) $hours < 0)) {
            throw ValidationException::withMessages([
                'file' => "Hours must be a number (row {$rowNumber}).",
            ]);
        }

        $position = $value('position');

        return [
            'row_number' => $rowNumber,
            'name' => trim((string) $value('name')),
            'external_id' => $externalId,
            'hours' => round((float) $hours, 2),
            'pay_rate' => $this->rate($value('pay_rate'), $rowNumber, required: true),
            'bill_rate' => $this->rate($value('bill_rate'), $rowNumber, required: false),
            'start_date' => $this->normalizeDate($value('start_date'), $rowNumber, 'start date'),
            'end_date' => $this->normalizeDate($value('end_date'), $rowNumber, 'end date'),
            'position' => ($position === null || trim((string) $position) === '') ? null : trim((string) $position),
            'days' => null,
        ];
    }

    private function rate(mixed $value, int $rowNumber, bool $required): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            if ($required) {
                throw ValidationException::withMessages(['file' => "Pay rate must be greater than zero (row {$rowNumber})."]);
            }

            return null;
        }

        $rate = is_numeric($value) ? (float) $value : (float) preg_replace('/[^0-9.]/', '', (string) $value);
        if ($rate <= 0) {
            throw ValidationException::withMessages([
                'file' => "Pay rate must be greater than zero (row {$rowNumber}).",
            ]);
        }

        return $rate;
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
