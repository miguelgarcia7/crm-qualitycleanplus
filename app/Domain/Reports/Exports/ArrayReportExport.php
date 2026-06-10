<?php

namespace App\Domain\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Generic one-sheet export: a heading row + pre-built data rows. Every report
 * export feeds this with the same rows its page renders, so the download
 * always matches the screen.
 */
class ArrayReportExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     */
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
    ) {}

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }
}
