<?php

namespace App\Domain\SystemReference\Support;

use Carbon\CarbonInterface;
use Closure;
use DateTimeZone;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use ReflectionFunction;
use ReflectionProperty;
use Throwable;

/**
 * The scheduled tasks as Admin reads them: each task's ->description() from
 * routes/console.php, how often and when it runs (in Chicago time and in UTC,
 * the zone most of them are written in), where it sits on a 24-hour day, and
 * the command or job that does the work.
 *
 * Read from the live schedule, so a task added there shows up here. The tasks
 * are only registered when the console kernel loads routes/console.php, which
 * a web request never does on its own — hence the bootstrap.
 */
class AutomationCatalog
{
    public const DISPLAY_TIMEZONE = 'America/Chicago';

    /**
     * @return list<array{name: string, source: string, schedule_timezone: string,
     *     cadence: string, cadence_utc: string, next_run: string, next_run_utc: string,
     *     timeline: array{chicago: array{start: float, end: float|null}, utc: array{start: float, end: float|null}}|null,
     *     evening_in_chicago: bool}>
     */
    public function all(): array
    {
        app(Kernel::class)->bootstrap();

        return collect(app(Schedule::class)->events())
            ->map(fn (Event $event): array => $this->describe($event))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Event $event): array
    {
        $zone = $this->zone($event);
        $next = $event->nextRunDate()->setTimezone($zone);
        $chicago = $next->copy()->setTimezone(self::DISPLAY_TIMEZONE);
        $utc = $next->copy()->setTimezone('UTC');

        return [
            'name' => (string) ($event->description ?? $event->command ?? 'Unnamed task'),
            'source' => $this->source($event),
            'schedule_timezone' => $zone === 'UTC' ? 'UTC' : 'Chicago time',
            'cadence' => $this->cadence($event, $next, self::DISPLAY_TIMEZONE),
            'cadence_utc' => $this->cadence($event, $next, 'UTC'),
            'next_run' => $chicago->format('D, M j \a\t g:i A'),
            'next_run_utc' => $utc->format('D, M j \a\t H:i'),
            'timeline' => $this->timeline($event, $next),
            // Written in UTC, so a "nightly" task lands in a Chicago evening.
            'evening_in_chicago' => $zone === 'UTC' && $this->isDaily($event) && $chicago->hour >= 17,
        ];
    }

    private function zone(Event $event): string
    {
        $zone = $event->timezone ?: config('app.timezone');

        return $zone instanceof DateTimeZone ? $zone->getName() : (string) $zone;
    }

    private function isDaily(Event $event): bool
    {
        return preg_match('/^\d+ \d+ \* \* \*$/', $event->expression) === 1;
    }

    /** "Daily at 7:15 PM" / "Every 5 minutes, weekdays 6:00 AM to 6:55 PM", in the given zone. */
    private function cadence(Event $event, CarbonInterface $next, string $zone): string
    {
        $fmt = $zone === 'UTC' ? 'H:i' : 'g:i A';

        if ($this->isDaily($event)) {
            return 'Daily at '.$next->copy()->setTimezone($zone)->format($fmt);
        }

        if ($band = $this->band($event, $next)) {
            [$every, $from, $to, $days] = $band;

            return "Every {$every} minutes, {$days} "
                .$from->copy()->setTimezone($zone)->format($fmt).' to '.$to->copy()->setTimezone($zone)->format($fmt);
        }

        return "On the schedule {$event->expression}";
    }

    /**
     * A repeating window like `*\/5 6-18 * * 1-5`: [every N minutes, first run, last run, which days].
     *
     * @return array{0: int, 1: CarbonInterface, 2: CarbonInterface, 3: string}|null
     */
    private function band(Event $event, CarbonInterface $next): ?array
    {
        if (preg_match('/^\*\/(\d+) (\d+)-(\d+) \* \* (\S+)$/', $event->expression, $m) !== 1) {
            return null;
        }

        $every = (int) $m[1];
        $from = $next->copy()->setTime((int) $m[2], 0);
        $to = $next->copy()->setTime((int) $m[3], 60 - $every);
        $days = match ($m[4]) {
            '1-5' => 'weekdays',
            '*' => 'every day',
            default => "on days {$m[4]}",
        };

        return [$every, $from, $to, $days];
    }

    /**
     * Where the task sits on a 24-hour day (hours from midnight), in each zone:
     * a point for a daily task, a span for a repeating window.
     *
     * @return array{chicago: array{start: float, end: float|null}, utc: array{start: float, end: float|null}}|null
     */
    private function timeline(Event $event, CarbonInterface $next): ?array
    {
        $hours = fn (CarbonInterface $t, string $zone): float => round($t->copy()->setTimezone($zone)->hour + $t->copy()->setTimezone($zone)->minute / 60, 3);

        if ($this->isDaily($event)) {
            return [
                'chicago' => ['start' => $hours($next, self::DISPLAY_TIMEZONE), 'end' => null],
                'utc' => ['start' => $hours($next, 'UTC'), 'end' => null],
            ];
        }

        if ($band = $this->band($event, $next)) {
            [$every, $from, $to] = $band;
            $span = fn (string $zone): array => ['start' => $hours($from, $zone), 'end' => round($hours($to, $zone) + $every / 60, 3)];

            return ['chicago' => $span(self::DISPLAY_TIMEZONE), 'utc' => $span('UTC')];
        }

        return null;
    }

    /** The artisan command, or the queued job's class, that does the work. */
    private function source(Event $event): string
    {
        if ($event->command !== null && preg_match("/artisan'?\s+'?([\w:.-]+)/", $event->command, $m) === 1) {
            return 'php artisan '.$m[1];
        }

        if ($event instanceof CallbackEvent) {
            try {
                $callback = (new ReflectionProperty(CallbackEvent::class, 'callback'))->getValue($event);
                $job = $callback instanceof Closure ? ((new ReflectionFunction($callback))->getClosureUsedVariables()['job'] ?? null) : null;

                if ($job !== null) {
                    return class_basename(is_string($job) ? $job : $job::class).' (queued job)';
                }
            } catch (Throwable) {
                // Fall through to the generic label.
            }
        }

        return 'Scheduled callback';
    }
}
