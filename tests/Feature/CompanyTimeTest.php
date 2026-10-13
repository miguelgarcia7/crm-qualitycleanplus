<?php

use App\Support\CompanyTime;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;

/*
 * ADR-0030: stored in UTC, shown and dated on the company's clock (Central),
 * or the property's for work at a property.
 */

/** Source lines under $dirs (relative to app/) that match $pattern, as "path:line: code". */
function linesMatching(array $dirs, string $pattern, array $allowed = []): array
{
    $hits = [];

    foreach ($dirs as $dir) {
        foreach (File::allFiles(app_path($dir)) as $file) {
            foreach (file($file->getPathname()) as $i => $line) {
                if (preg_match($pattern, $line) !== 1) {
                    continue;
                }
                foreach ($allowed as $ok) {
                    if (str_contains($line, $ok)) {
                        continue 2;
                    }
                }
                $hits[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($i + 1).': '.trim($line);
            }
        }
    }

    return $hits;
}

it('reads the company clock in Central time while UTC is already tomorrow', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 03:00', 'UTC')); // 10 PM Oct 12 in Chicago

    expect(CompanyTime::zone())->toBe('America/Chicago')
        ->and(CompanyTime::now()->format('Y-m-d H:i'))->toBe('2026-10-12 22:00')
        ->and(CompanyTime::today()->utc()->format('Y-m-d H:i'))->toBe('2026-10-12 05:00')
        ->and(CompanyTime::date()->format('Y-m-d H:i e'))->toBe('2026-10-12 00:00 UTC')
        ->and(CompanyTime::of(CarbonImmutable::parse('2026-10-12 20:00', 'UTC'))?->format('g:i A'))->toBe('3:00 PM')
        ->and(CompanyTime::of(null))->toBeNull();
});

it('runs every scheduled task on the company clock', function () {
    app(Kernel::class)->bootstrap();

    $zones = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn ($event) => [(string) $event->description => (string) $event->timezone]);

    expect($zones)->not->toBeEmpty()
        ->and($zones->unique()->values()->all())->toBe(['America/Chicago']);
});

it('never shows a stored timestamp in raw UTC on a screen', function () {
    // A timestamp column formatted for display has to go through
    // CompanyTime::of() (office time) or setTimezone() (a property's time).
    $hits = linesMatching(
        ['Http', 'Domain/Dashboards', 'Domain/People/Support'],
        '/_at(_utc)?\)?\??->(format|toDayDateTimeString|toDateTimeString|toDateString|toFormattedDateString|toFormattedDayDateString)\(/',
        ['CompanyTime::of(', 'setTimezone('],
    );

    expect($hits)->toBe([]);
});

it('never takes today from the UTC clock', function () {
    // "Today", this week, this month and the year come from CompanyTime or the
    // property's own date. Bare now() is only for durations.
    $hits = linesMatching(
        ['Http', 'Domain', 'Console'],
        '/(?<![:\w>])(now|today)\(\)->(toDateString|startOf\w+|format|year|month)\b|Carbon(Immutable)?::(now|today)\(\)->(toDateString|startOf\w+|format|year|month)\b/',
        [],
    );

    expect(array_values(array_filter($hits, fn (string $hit): bool => ! str_starts_with($hit, 'app/Domain/LegacyImport/'))))->toBe([]);
});
