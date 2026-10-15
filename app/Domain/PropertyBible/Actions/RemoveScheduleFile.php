<?php

namespace App\Domain\PropertyBible\Actions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\WeekSchedule;
use App\Domain\Shared\Models\File;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Takes a file off a week's schedule, while the week isn't over. The file is
 * deleted from storage; the removal goes on the property's History.
 */
class RemoveScheduleFile
{
    public function handle(WeekSchedule $schedule, File $file, Person $by): void
    {
        if (! $schedule->isOpen()) {
            throw ValidationException::withMessages(['file' => 'That week is over. Its schedule can no longer change.']);
        }

        Storage::disk($file->disk)->delete($file->path);
        $name = $file->original_name;
        $file->forceDelete();

        $range = UploadWeekSchedule::range(CarbonImmutable::parse($schedule->week_start->toDateString()));
        activity('property_bible')->performedOn($schedule->property)->causedBy($by)->event('schedule_file_removed')
            ->log("Removed \"{$name}\" from the schedule for {$range}");
    }
}
