<?php

namespace App\Domain\Demo\Actions;

use App\Domain\Demo\Concerns\TravelsInTime;
use App\Domain\Demo\DemoRoster;
use App\Domain\Imports\Actions\CommitImport;
use App\Domain\Imports\Actions\CreateImportBatch;
use App\Domain\Imports\Enums\ImportBatchStatus;
use App\Domain\Imports\Models\ImportBatch;
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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The import-only demo company's weekly hours (Lakeside Suites): writes the
 * Excel file its payroll system would send and runs it through the real
 * import — upload, match by hotel ID, commit — so its history has import
 * batches, approved timesheets and invoices like a real one.
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

            $rows = $this->rows($property, $week);
            if ($rows === []) {
                continue;
            }

            $dir = storage_path('app/demo-imports/'.uniqid());
            File::ensureDirectoryExists($dir);
            $path = $dir.'/'.$property->name.' – '.$week->format('M j').'.xlsx';
            $sheet = new Spreadsheet;
            $sheet->getActiveSheet()->fromArray([['Name', 'Employee ID', 'Total Hours', 'Pay Rate', 'Start Date', 'End Date', 'Position'], ...$rows], null, 'A1');
            (new Xlsx($sheet))->save($path);

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
     * One line per contractor who worked that week: their weekly total from
     * the shift plan, at their work order's pay rate (so every row matches).
     *
     * @return list<array<int, string|float>>
     */
    private function rows(Property $property, CarbonImmutable $weekStart): array
    {
        $tz = $property->timezone;
        $weekEnd = $weekStart->addDays(6);
        $rows = [];

        $workOrders = WorkOrder::query()
            ->where('property_id', $property->id)
            ->where('status', WorkOrderStatus::Active)
            ->whereDate('start_date', '<=', $weekEnd->toDateString())
            ->with('person', 'position')
            ->orderBy('id')
            ->get();

        foreach ($workOrders as $workOrder) {
            $contractor = DemoRoster::contractor((string) $workOrder->person?->email);
            if ($contractor === null || ! isset($contractor['hotel_id'])) {
                continue;
            }

            $minutes = 0;
            for ($day = $weekStart; $day <= $weekEnd; $day = $day->addDay()) {
                if ($day->toDateString() < $workOrder->start_date->toDateString()) {
                    continue;
                }
                foreach (DemoRoster::shiftsFor($contractor['shift'], $workOrder->person_id, $day, $tz) as [$in, $out]) {
                    $minutes += (int) $in->diffInMinutes($out);
                }
            }
            if ($minutes === 0) {
                continue;
            }

            $rows[] = [
                (string) $workOrder->person?->name,
                $contractor['hotel_id'],
                round($minutes / 60, 2),
                round($workOrder->pay_rate / 100, 2),
                $weekStart->toDateString(),
                $weekEnd->toDateString(),
                (string) $workOrder->position?->name,
            ];
        }

        return $rows;
    }
}
