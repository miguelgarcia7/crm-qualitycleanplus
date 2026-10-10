<?php

namespace App\Domain\SystemReference\Support;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;

/**
 * The scheduled tasks as Admin reads them: each task's ->description() from
 * routes/console.php, how often it runs and when it runs next, in Chicago time.
 *
 * Read from the live schedule, so a task added there shows up here. The tasks
 * are only registered when the console kernel loads routes/console.php, which
 * a web request never does on its own — hence the bootstrap.
 */
class AutomationCatalog
{
    public const DISPLAY_TIMEZONE = 'America/Chicago';

    /**
     * @return list<array{name: string, cadence: string, next_run: string}>
     */
    public function all(): array
    {
        app(Kernel::class)->bootstrap();

        return collect(app(Schedule::class)->events())
            ->map(fn (Event $event): array => [
                'name' => (string) ($event->description ?? $event->command ?? 'Unnamed task'),
                'cadence' => $this->cadence($event),
                'next_run' => $event->nextRunDate()->setTimezone(self::DISPLAY_TIMEZONE)->format('D, M j \a\t g:i A'),
            ])
            ->values()
            ->all();
    }

    private function cadence(Event $event): string
    {
        if (preg_match('/^(\d+) (\d+) \* \* \*$/', $event->expression, $m) === 1) {
            $time = now($event->timezone)->setTime((int) $m[2], (int) $m[1])->setTimezone(self::DISPLAY_TIMEZONE);

            return 'Daily at '.$time->format('g:i A');
        }

        if ($event->expression === '*/5 6-18 * * 1-5') {
            return 'Every 5 minutes, weekdays 6 AM to 7 PM';
        }

        return "On the schedule {$event->expression}";
    }
}
