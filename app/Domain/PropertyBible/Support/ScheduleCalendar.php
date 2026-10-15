<?php

namespace App\Domain\PropertyBible\Support;

use App\Domain\PropertyBible\Actions\UploadWeekSchedule;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\WeekSchedule;
use App\Domain\Shared\Models\File;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;

/**
 * The property manager's Schedules calendar (QC Minute): the work weeks a
 * month touches, in the hotel's own week (Mon–Sun unless its closing day says
 * otherwise), each with its days, the files uploaded for it, its note, and
 * whether it can still change. Also the three-week summary on the dashboard.
 */
class ScheduleCalendar
{
    /**
     * @return array{label: string, previous: string, next: string|null, days: list<string>, weeks: list<array<string, mixed>>}
     */
    public function month(Property $property, CarbonImmutable $month): array
    {
        $today = CarbonImmutable::parse($property->localDate());
        $thisWeek = $property->weekStartFor($today);
        $first = $month->startOfMonth();
        $last = $month->endOfMonth()->startOfDay();

        $starts = [];
        for ($w = $property->weekStartFor($first); $w->lte($last); $w = $w->addWeek()) {
            $starts[] = $w;
        }
        $schedules = $this->schedules($property, $starts);

        $latest = $thisWeek->addWeeks(UploadWeekSchedule::WEEKS_AHEAD);

        return [
            'label' => $month->format('F Y'),
            'previous' => $month->subMonthNoOverflow()->format('Y-m'),
            // Nothing to plan past the upload window.
            'next' => $month->addMonthNoOverflow()->startOfMonth()->lte($latest) ? $month->addMonthNoOverflow()->format('Y-m') : null,
            'days' => array_map(fn (CarbonImmutable $d): string => $d->format('D'), array_map(fn (int $i) => $starts[0]->addDays($i), range(0, 6))),
            'weeks' => array_map(fn (CarbonImmutable $start): array => $this->week($start, $schedules[$start->toDateString()] ?? null, $thisWeek, $today, $month, $latest), $starts),
        ];
    }

    /**
     * This week and the two after it, for the dashboard card.
     *
     * @return list<array{week_start: string, label: string, when: string, files: int}>
     */
    public function upcoming(Property $property): array
    {
        $thisWeek = $property->weekStartFor(CarbonImmutable::parse($property->localDate()));
        $starts = [$thisWeek, $thisWeek->addWeek(), $thisWeek->addWeeks(2)];
        $schedules = $this->schedules($property, $starts);

        return array_map(fn (CarbonImmutable $start, int $i): array => [
            'week_start' => $start->toDateString(),
            'label' => UploadWeekSchedule::range($start),
            'when' => ['This week', 'Next week', 'In 2 weeks'][$i],
            'files' => ($schedules[$start->toDateString()] ?? null)?->files->count() ?? 0,
        ], $starts, array_keys($starts));
    }

    /**
     * @param  list<CarbonImmutable>  $starts
     * @return array<string, WeekSchedule>
     */
    private function schedules(Property $property, array $starts): array
    {
        if ($starts === []) {
            return [];
        }

        return WeekSchedule::query()
            ->where('property_id', $property->id)
            ->whereDate('week_start', '>=', $starts[0]->toDateString())
            ->whereDate('week_start', '<=', end($starts)->toDateString())
            ->with('files.uploadedBy:id,name')
            ->get()
            ->keyBy(fn (WeekSchedule $s): string => $s->week_start->toDateString())
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function week(CarbonImmutable $start, ?WeekSchedule $schedule, CarbonImmutable $thisWeek, CarbonImmutable $today, CarbonImmutable $month, CarbonImmutable $latest): array
    {
        $weeksAway = (int) round($thisWeek->diffInDays($start) / 7);
        $files = $schedule === null ? collect() : $schedule->files;

        return [
            'week_start' => $start->toDateString(),
            'label' => UploadWeekSchedule::range($start),
            'long_label' => $start->format('D, M j').' – '.$start->addDays(6)->format('D, M j'),
            'when' => match (true) {
                $weeksAway === 0 => 'This week',
                $weeksAway === 1 => 'Next week',
                $weeksAway === -1 => 'Last week',
                $weeksAway > 1 => "In {$weeksAway} weeks",
                default => null,
            },
            'is_past' => $weeksAway < 0,
            'is_current' => $weeksAway === 0,
            'is_next' => $weeksAway === 1,
            // Files change until the week is over, and only within the window.
            'can_change' => $start->addDays(6)->gte($today) && $start->lte($latest),
            'days' => array_map(fn (int $i): array => [
                'date' => $start->addDays($i)->toDateString(),
                'day' => $start->addDays($i)->day,
                'in_month' => $start->addDays($i)->month === $month->month,
                'today' => $start->addDays($i)->isSameDay($today),
            ], range(0, 6)),
            'note' => $schedule?->note,
            'files' => $files->map(fn (File $f): array => [
                'id' => $f->id,
                'name' => $f->original_name,
                'type' => self::kind($f),
                'size' => $f->size,
                'by' => $f->uploadedBy?->name,
                'at' => CompanyTime::of($f->created_at)?->format('M j'),
            ])->values()->all(),
        ];
    }

    /** A short badge for the file's kind: PDF, XLS, DOC, JPG… */
    public static function kind(File $file): string
    {
        $ext = strtolower(pathinfo((string) $file->original_name, PATHINFO_EXTENSION));

        return match ($ext) {
            'xlsx', 'xls', 'csv' => 'XLS',
            'docx', 'doc' => 'DOC',
            'jpeg', 'jpg', 'heic', 'heif' => 'JPG',
            '' => 'FILE',
            default => strtoupper(substr($ext, 0, 4)),
        };
    }
}
