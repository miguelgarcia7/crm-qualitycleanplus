<?php

namespace App\Notifications;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Actions\UploadWeekSchedule;
use App\Domain\PropertyBible\Models\WeekSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;

/**
 * Tells a hotel's recruiter its property manager uploaded the schedule for a
 * work week, with a link that downloads it (one file, or the week's files as a
 * zip). Database channel.
 */
class ScheduleUploadedNotification extends AppNotification
{
    use Queueable;

    /**
     * @param  list<string>  $fileNames  the files just uploaded
     */
    public function __construct(
        public WeekSchedule $schedule,
        public array $fileNames,
        public Person $by,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Schedules;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $range = UploadWeekSchedule::range(CarbonImmutable::parse($this->schedule->week_start->toDateString()));
        $property = $this->schedule->property?->name;
        $files = count($this->fileNames) === 1 ? $this->fileNames[0] : count($this->fileNames).' files';

        return [
            'type' => 'schedule_uploaded',
            'category' => $this->category()->value,
            'schedule_id' => $this->schedule->id,
            'property_id' => $this->schedule->property_id,
            'message' => "{$this->by->name} uploaded the schedule for {$property}, {$range}: {$files}."
                .($this->schedule->note ? " Note: {$this->schedule->note}" : ''),
        ];
    }
}
