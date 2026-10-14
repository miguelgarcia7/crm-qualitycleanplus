<?php

namespace App\Domain\Demo\Actions;

use App\Domain\Demo\Concerns\TravelsInTime;
use App\Domain\Demo\DemoRoster;
use App\Domain\Imports\Actions\CommitImport;
use App\Domain\Imports\Actions\CreateImportBatch;
use App\Domain\Imports\Enums\ImportBatchStatus;
use App\Domain\Imports\Models\ImportBatch;
use App\Domain\Imports\Support\HourImportTemplate;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Enums\PropertyTimeSource;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The import-only demo company's weekly hours (Lakeside Suites): downloads the
 * week's file (HourImportTemplate), types each person's hours per day, and runs
 * it through the real import — upload, match by hotel ID, commit — so its
 * history has import batches, approved timesheets and invoices like a real one,
 * with holiday hours on their day.
 *
 * A week is imported by its recruiter the Monday after the following week
 * ends, so last week is always still waiting: that's the one to upload by hand
 * in a demo. A week someone already imported is left alone. Hours follow each
 * contractor's DemoRoster shift plan, so they agree with any rerun.
 */
class ImportDemoHours
{
    use TravelsInTime;

    public function __construct(
        private CreateImportBatch $createBatch,
        private CommitImport $commit,
        private HourImportTemplate $template,
    ) {}

    /** @return int weeks imported */
    public function handle(Property $property, CarbonImmutable $from, CarbonImmutable $until): int
    {
        if ($property->time_source !== PropertyTimeSource::Import) {
            return 0;
        }

        $recruiter = Person::query()
            ->whereHas('propertyAssignments', fn ($q) => $q->where('property_id', $property->id)->where('role', PropertyAssignmentRole::Recruiter->value))
            ->first();
        if ($recruiter === null) {
            return 0;
        }

        $tz = $property->timezone;
        $imported = 0;

        for ($week = $property->weekStartFor($from->setTimezone($tz)); ; $week = $week->addWeek()) {
            $importAt = CarbonImmutable::parse($week->addDays(6)->toDateString(), $tz)->addDays(8)->setTime(10, 0);
            if ($importAt > $until) {
                break;
            }

            $period = $this->period($property, $week);
            $done = ImportBatch::query()->where('payroll_period_id', $period->id)->where('status', ImportBatchStatus::Applied)->exists();
            if ($done || ! $period->status->isEditable()) {
                continue;
            }

            $hours = $this->hours($property, $week);
            if ($hours === []) {
                continue;
            }

            // The week's file, filled in the way the office would: hours per
            // day on each listed person's row (found by hotel ID).
            $book = $this->template->build($property, $period);
            $sheet = $book->getSheetByName('Hours');
            for ($row = HourImportTemplate::HEADER_ROW + 1; $sheet !== null && $row <= $sheet->getHighestRow(); $row++) {
                foreach ($hours[(string) $sheet->getCell("B{$row}")->getValue()] ?? [] as $day => $value) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex(5 + $day)."{$row}", $value);
                }
            }

            $dir = storage_path('app/demo-imports/'.uniqid());
            File::ensureDirectoryExists($dir);
            $path = $dir.'/'.$this->template->fileName($property, $period);
            (new Xlsx($book))->save($path);

            try {
                $this->at($importAt, function () use ($property, $period, $path, $recruiter): void {
                    $batch = $this->createBatch->handle($property, $period, $path, $recruiter);
                    $this->commit->handle($batch, $recruiter);
                });
            } finally {
                File::deleteDirectory($dir);
            }

            $imported++;
        }

        return $imported;
    }

    private function period(Property $property, CarbonImmutable $weekStart): PayrollPeriod
    {
        return $property->payrollPeriods()->whereDate('week_start', $weekStart->toDateString())->first()
            ?? $property->payrollPeriods()->create([
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekStart->addDays(6)->toDateString(),
                'status' => PayrollPeriodStatus::Open,
            ]);
    }

    /**
     * Each contractor's hours that week, by hotel ID and day (0 = the week's
     * first day), from their DemoRoster shift plan.
     *
     * @return array<string, array<int, float>>
     */
    private function hours(Property $property, CarbonImmutable $weekStart): array
    {
        $tz = $property->timezone;
        $hours = [];

        $workOrders = WorkOrder::query()
            ->where('property_id', $property->id)
            ->where('status', WorkOrderStatus::Active)
            ->whereDate('start_date', '<=', $weekStart->addDays(6)->toDateString())
            ->with('person')
            ->get();

        foreach ($workOrders as $workOrder) {
            $contractor = DemoRoster::contractor((string) $workOrder->person?->email);
            if ($contractor === null || ! isset($contractor['hotel_id'])) {
                continue;
            }

            for ($i = 0; $i < 7; $i++) {
                $day = $weekStart->addDays($i);
                if ($day->toDateString() < $workOrder->start_date->toDateString()) {
                    continue;
                }
                $minutes = 0;
                foreach (DemoRoster::shiftsFor($contractor['shift'], $workOrder->person_id, $day, $tz) as [$in, $out]) {
                    $minutes += (int) $in->diffInMinutes($out);
                }
                if ($minutes > 0) {
                    // Hotels report to the quarter hour.
                    $hours[$contractor['hotel_id']][$i] = round($minutes / 15) / 4;
                }
            }
        }

        return $hours;
    }
}
