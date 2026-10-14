<?php

namespace App\Domain\Imports\Support;

use App\Domain\People\Models\PersonExternalId;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The hour-import file for one import-only hotel and week (40-flows/import-hours.md):
 * an "Hours" tab already filled in with everyone working there that week — name,
 * hotel ID, position, pay rate — so the person only types hours per day, and a
 * "How to fill this in" tab. Holidays the hotel observes are marked on their day.
 * The importer reads this layout back (HourImportParser).
 */
class HourImportTemplate
{
    /** The header row; the rows above it are the title and the instructions. */
    public const HEADER_ROW = 4;

    /** Empty yellow rows left for people who aren't on the list yet. */
    private const BLANK_ROWS = 10;

    private const GRAY = 'EEF0EE';

    private const YELLOW = 'FFF7D1';

    private const HEADER = '3F4F4B';

    private const HOLIDAY = 'B5651D';

    private const HOLIDAY_CELL = 'FDE3C8';

    private const TOTAL = 'E3EBE7';

    public function build(Property $property, PayrollPeriod $period): Spreadsheet
    {
        $start = CarbonImmutable::parse($period->week_start->toDateString());
        $days = array_map(fn (int $i): CarbonImmutable => $start->addDays($i), range(0, 6));
        $holidays = $property->holidayDates(array_values(array_unique([$days[0]->year, $days[6]->year])));

        $book = new Spreadsheet;
        $book->getProperties()
            ->setTitle("{$property->name} hours, week of {$start->format('M j, Y')}")
            ->setCreator((string) config('app.name'))
            ->setCustomProperty('qcp_property_id', (string) $property->id)
            ->setCustomProperty('qcp_week_start', $start->toDateString());

        $this->hours($book->getActiveSheet(), $property, $period, $days, $holidays);
        $this->howTo($book->createSheet(), $property, $days, $holidays);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    public function fileName(Property $property, PayrollPeriod $period): string
    {
        $name = trim((string) preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $property->name));

        return "{$name} – {$period->week_start->format('M j')}.xlsx";
    }

    /**
     * Everyone with a work order at the hotel that week, by name.
     *
     * @return list<array{name: string, hotel_id: string, position: string, pay_rate: float}>
     */
    public function people(Property $property, PayrollPeriod $period): array
    {
        $workOrders = WorkOrder::query()
            ->where('property_id', $property->id)
            ->whereDate('start_date', '<=', $period->week_end->toDateString())
            ->where(fn ($q) => $q->where('status', WorkOrderStatus::Active)
                ->orWhereDate('end_date', '>=', $period->week_start->toDateString()))
            ->with('person:id,name', 'position:id,name')
            ->get();

        $ids = PersonExternalId::query()
            ->where('property_id', $property->id)
            ->whereIn('person_id', $workOrders->pluck('person_id'))
            ->pluck('external_id', 'person_id');

        return $workOrders
            ->map(fn (WorkOrder $w): array => [
                'name' => (string) $w->person?->name,
                'hotel_id' => (string) ($ids[$w->person_id] ?? ''),
                'position' => (string) $w->position?->name,
                'pay_rate' => round($w->pay_rate / 100, 2),
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @param  list<CarbonImmutable>  $days
     * @param  array<string, string>  $holidays
     */
    private function hours(Worksheet $sheet, Property $property, PayrollPeriod $period, array $days, array $holidays): void
    {
        $sheet->setTitle('Hours');
        $h = self::HEADER_ROW;
        $last = 'M';

        $sheet->setCellValue('A1', "{$property->name} · Hours for {$days[0]->format('D, M j')} – {$days[6]->format('D, M j, Y')}");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->setCellValue('A2', 'Type hours in the yellow cells (7.5 = 7 h 30 min). Leave days off blank. Don\'t change the gray columns. Questions? See the "How to fill this in" tab.');

        $headers = ['Name', 'Hotel ID', 'Position', 'Pay Rate'];
        foreach ($days as $day) {
            $headers[] = $day->format('D n/j').(isset($holidays[$day->toDateString()]) ? ' Holiday' : '');
        }
        $headers[] = 'Total';
        $headers[] = 'Notes';
        $sheet->fromArray($headers, null, "A{$h}");
        $this->fill($sheet, "A{$h}:{$last}{$h}", self::HEADER);
        $sheet->getStyle("A{$h}:{$last}{$h}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("E{$h}:L{$h}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $people = $this->people($property, $period);
        $first = $h + 1;
        $row = $first;
        foreach ($people as $person) {
            $sheet->fromArray([$person['name'], $person['hotel_id'], $person['position'], $person['pay_rate']], null, "A{$row}");
            $row++;
        }
        $lastPerson = $row - 1;
        $end = $row + self::BLANK_ROWS - 1;

        if ($lastPerson >= $first) {
            $this->fill($sheet, "A{$first}:D{$lastPerson}", self::GRAY);
        }
        $this->fill($sheet, "A{$row}:D{$end}", self::YELLOW);
        $this->fill($sheet, "E{$first}:K{$end}", self::YELLOW);
        $this->fill($sheet, "M{$first}:M{$end}", self::YELLOW);
        $this->fill($sheet, "L{$first}:L{$end}", self::TOTAL);
        $sheet->getStyle("L{$first}:L{$end}")->getFont()->setBold(true);

        foreach ($days as $i => $day) {
            if (isset($holidays[$day->toDateString()])) {
                $col = Coordinate::stringFromColumnIndex(5 + $i);
                $this->fill($sheet, "{$col}{$h}", self::HOLIDAY);
                $this->fill($sheet, "{$col}{$first}:{$col}{$end}", self::HOLIDAY_CELL);
            }
        }

        for ($r = $first; $r <= $end; $r++) {
            $sheet->setCellValue("L{$r}", "=IF(COUNT(E{$r}:K{$r})=0,\"\",SUM(E{$r}:K{$r}))");
        }
        $sheet->getStyle("D{$first}:D{$end}")->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle("E{$first}:L{$end}")->getNumberFormat()->setFormatCode('0.##');

        // Hours must be a number of hours in a day: catches "9-5" or "8h" as it's typed.
        $rule = new DataValidation;
        $rule->setType(DataValidation::TYPE_DECIMAL)
            ->setOperator(DataValidation::OPERATOR_BETWEEN)
            ->setFormula1('0')
            ->setFormula2('24')
            ->setAllowBlank(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Hours')
            ->setError('Type the hours worked as a number, like 8 or 7.5 (7 h 30 min).');
        $sheet->setDataValidation("E{$first}:K{$end}", $rule);

        $sheet->setCellValue('A'.($end + 1), 'New person? Use an empty yellow row: name, hotel ID and position. The pay rate can stay blank.');
        $sheet->getStyle('A'.($end + 1))->getFont()->setItalic(true)->getColor()->setRGB('555555');

        $sheet->getStyle("A{$h}:{$last}{$end}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D4D4D4');
        foreach (['A' => 24, 'B' => 12, 'C' => 24, 'D' => 10, 'L' => 9, 'M' => 30] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        foreach (range('E', 'K') as $col) {
            $sheet->getColumnDimension($col)->setWidth(12);
        }
        $sheet->freezePane('E'.$first);
        $sheet->setSelectedCell('E'.$first);
    }

    /**
     * @param  list<CarbonImmutable>  $days
     * @param  array<string, string>  $holidays
     */
    private function howTo(Worksheet $sheet, Property $property, array $days, array $holidays): void
    {
        $sheet->setTitle('How to fill this in');
        $week = "{$days[0]->format('D, M j')} – {$days[6]->format('D, M j, Y')}";

        $holidayText = 'A day marked Holiday in orange is paid and billed at the holiday rate. Type the hours as usual; holiday hours never count as overtime.';
        $thisWeek = array_values(array_filter($days, fn (CarbonImmutable $d): bool => isset($holidays[$d->toDateString()])));
        if ($thisWeek !== []) {
            $holidayText .= ' This week: '.implode(', ', array_map(fn (CarbonImmutable $d): string => "{$holidays[$d->toDateString()]} ({$d->format('D, M j')})", $thisWeek)).'.';
        }

        $rows = [
            ['How to fill this in', null],
            [null, null],
            ['The columns', null],
            ['Name', 'The person\'s name. Only so you can tell who is who.'],
            ['Hotel ID', 'Their ID in the hotel\'s payroll system. Don\'t change it: it\'s how we match the row to the person.'],
            ['Position', 'What they did this week. Leave it as it is unless they worked a different job.'],
            ['Pay Rate', 'Dollars per hour, from their work order. Change it only if their rate really changed; you\'ll confirm it after uploading.'],
            ['Mon … Sun', 'Hours worked that day, in hours: 8, 7.5 (7 h 30 min), 7.25 (7 h 15 min). Leave days off blank. No times like 9:00–5:00.'],
            ['Total', 'Adds up the days for you. Don\'t type in it.'],
            ['Notes', 'Optional, for anything the office should know. Not shown on the invoice.'],
            [null, null],
            ['Overtime and holidays', null],
            ['Overtime', 'There\'s no overtime column. Hours over 40 in the week are paid and billed as overtime on their own.'],
            ['Holidays', $holidayText],
            [null, null],
            ['Before you upload', null],
            ['New people', 'Use an empty yellow row: name, hotel ID and position. After uploading you\'ll link them to an existing person or add them.'],
            ['Someone didn\'t work', 'Leave their days blank. A row with no hours is skipped.'],
            ['Keep it to this week', "This file is for {$week} at {$property->name} only. Download a new one for another week."],
            ['Upload', 'Back office → Billing → Hour Imports → New import. Nothing is saved until you review and confirm.'],
        ];
        $sheet->fromArray($rows, null, 'A1');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A4:A20')->getFont()->setBold(true);
        foreach ([3, 12, 16] as $r) {
            $this->fill($sheet, "A{$r}:B{$r}", self::TOTAL);
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        }
        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(120);
        $sheet->getStyle('B1:B20')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('A1:A20')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    }

    private function fill(Worksheet $sheet, string $range, string $rgb): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
    }
}
