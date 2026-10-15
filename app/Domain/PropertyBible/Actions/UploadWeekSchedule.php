<?php

namespace App\Domain\PropertyBible\Actions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\WeekSchedule;
use App\Domain\Shared\Models\File;
use App\Notifications\ScheduleUploadedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * A property manager adds their schedule for a work week (QC Minute →
 * Schedules): any number of files, in whatever form the hotel keeps it, and an
 * optional note. Allowed from the current week up to WEEKS_AHEAD ahead; a week
 * that is over can't change. The hotel's recruiters are told, with a link to
 * download it, and the change goes on the property's History.
 */
class UploadWeekSchedule
{
    /** How far ahead a schedule can be uploaded. */
    public const WEEKS_AHEAD = 8;

    /**
     * @param  list<UploadedFile>  $files
     */
    public function handle(Property $property, CarbonImmutable $weekStart, array $files, ?string $note, Person $by): WeekSchedule
    {
        $weekStart = $property->weekStartFor($weekStart);
        $today = CarbonImmutable::parse($property->localDate());
        if ($weekStart->addDays(6)->lt($today)) {
            throw ValidationException::withMessages(['files' => 'That week is over. Its schedule can no longer change.']);
        }
        if ($weekStart->gt($property->weekStartFor($today)->addWeeks(self::WEEKS_AHEAD))) {
            throw ValidationException::withMessages(['files' => 'Schedules can be uploaded up to '.self::WEEKS_AHEAD.' weeks ahead.']);
        }

        $note = $note === null || trim($note) === '' ? null : trim($note);

        $schedule = DB::transaction(function () use ($property, $weekStart, $files, $note, $by): WeekSchedule {
            // whereDate: a date column can come back with a time part (SQLite).
            $schedule = WeekSchedule::query()->where('property_id', $property->id)->whereDate('week_start', $weekStart->toDateString())->first()
                ?? WeekSchedule::create(['property_id' => $property->id, 'week_start' => $weekStart->toDateString(), 'created_by' => $by->id]);
            $schedule->fill(['note' => $note, 'updated_by' => $by->id])->save();

            $disk = (string) config('filesystems.default');
            foreach ($files as $upload) {
                File::create([
                    'fileable_type' => $schedule->getMorphClass(),
                    'fileable_id' => $schedule->id,
                    'disk' => $disk,
                    'path' => $upload->store("schedules/{$property->id}", $disk),
                    'original_name' => $upload->getClientOriginalName(),
                    'mime_type' => $upload->getClientMimeType(),
                    'size' => $upload->getSize(),
                    'uploaded_by' => $by->id,
                ]);
            }

            return $schedule;
        });

        $range = self::range($weekStart);
        activity('property_bible')->performedOn($property)->causedBy($by)->event('schedule_uploaded')
            ->log($files === []
                ? "Updated the note on the schedule for {$range}"
                : 'Uploaded '.count($files).' schedule '.(count($files) === 1 ? 'file' : 'files')." for {$range}");

        if ($files !== []) {
            $recruiters = Person::query()
                ->whereHas('propertyAssignments', fn ($q) => $q->where('property_id', $property->id)->where('role', PropertyAssignmentRole::Recruiter->value))
                ->get();
            if ($recruiters->isNotEmpty()) {
                Notification::send($recruiters, new ScheduleUploadedNotification($schedule, array_map(fn (UploadedFile $f): string => $f->getClientOriginalName(), $files), $by));
            }
        }

        return $schedule;
    }

    /** "Oct 19 – 25", or "Oct 26 – Nov 1" across months. */
    public static function range(CarbonImmutable $weekStart): string
    {
        $end = $weekStart->addDays(6);

        return $weekStart->format('M j').' – '.($end->month === $weekStart->month ? $end->format('j') : $end->format('M j'));
    }
}
