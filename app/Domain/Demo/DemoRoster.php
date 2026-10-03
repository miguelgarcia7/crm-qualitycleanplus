<?php

namespace App\Domain\Demo;

use Carbon\CarbonImmutable;

/**
 * The Acme Hotel demo cast (docs/80-plan/demo-environment.md): one login per
 * role, five contractors, and each contractor's daily shift plan. The single
 * source for AcmeHotelSeeder (who exists) and SimulateClock (when they punch).
 */
final class DemoRoster
{
    public const PROPERTY = 'Acme Hotel';

    /**
     * One login per non-contractor role: role => [email, name, months employed].
     *
     * @var array<string, array{0: string, 1: string, 2: int|null}>
     */
    public const STAFF = [
        'super_admin' => ['super-admin@example.com', 'Sam Super', 48],
        'admin' => ['admin@example.com', 'Ada Admin', 40],
        'office_manager' => ['office-manager@example.com', 'Olivia Office', 30],
        'front_desk' => ['front-desk@example.com', 'Frank Desk', 4],
        'hr' => ['hr@example.com', 'Hannah HR', 26],
        'payroll' => ['payroll@example.com', 'Paul Payroll', 14],
        'recruiter' => ['recruiter@example.com', 'Rita Recruiter', 18],
        'w2_employee' => ['w2@example.com', 'Wendy Worker', 9],
        'property_manager' => ['pm@example.com', 'Pat Manager', null], // the hotel's manager, not QCP staff
    ];

    /**
     * Acme's Bible rates, cents/hour: position slug => [pay, bill]. OT is 1.5×.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    public const RATES = [
        'housekeeper' => [1600, 2600],
        'houseman' => [1700, 2750],
        'laundry-attendant' => [1550, 2500],
        'public-space-attendant' => [1650, 2650],
        'banquet-server' => [1800, 2950],
    ];

    /**
     * Five contractors, each on one Acme work order. `rate` overrides the Bible
     * rate (cents: pay, bill) where a placement was negotiated; `shift` picks
     * the plan in shiftsFor(); `method` is how they punch — QR on their phone or
     * the lobby tablet.
     *
     * @var list<array{name: string, email: string, phone: string, position: string, rate: array{0: int, 1: int}|null, shift: string, method: string, started_weeks_ago: int}>
     */
    public const CONTRACTORS = [
        ['name' => 'Maria Lopez', 'email' => 'maria.lopez@example.com', 'phone' => '(312) 555-0141', 'position' => 'housekeeper', 'rate' => null, 'shift' => 'day', 'method' => 'qr', 'started_weeks_ago' => 22],
        ['name' => 'James Carter', 'email' => 'james.carter@example.com', 'phone' => '(312) 555-0142', 'position' => 'houseman', 'rate' => [1850, 2950], 'shift' => 'long', 'method' => 'qr', 'started_weeks_ago' => 17],
        ['name' => 'Aisha Brown', 'email' => 'aisha.brown@example.com', 'phone' => '(312) 555-0143', 'position' => 'laundry-attendant', 'rate' => null, 'shift' => 'day', 'method' => 'tablet', 'started_weeks_ago' => 13],
        ['name' => 'Tom Nguyen', 'email' => 'tom.nguyen@example.com', 'phone' => '(312) 555-0144', 'position' => 'public-space-attendant', 'rate' => [1700, 2700], 'shift' => 'day', 'method' => 'tablet', 'started_weeks_ago' => 9],
        ['name' => 'Sofia Ramirez', 'email' => 'sofia.ramirez@example.com', 'phone' => '(312) 555-0145', 'position' => 'banquet-server', 'rate' => null, 'shift' => 'day', 'method' => 'qr', 'started_weeks_ago' => 3],
    ];

    /** @return array{name: string, email: string, phone: string, position: string, rate: array{0: int, 1: int}|null, shift: string, method: string, started_weeks_ago: int}|null */
    public static function contractor(string $email): ?array
    {
        foreach (self::CONTRACTORS as $contractor) {
            if ($contractor['email'] === $email) {
                return $contractor;
            }
        }

        return null;
    }

    /**
     * A contractor's punches for one local date: [clock in, clock out] pairs
     * split by an unpaid lunch, Monday–Friday. Deterministic per person + date,
     * so every simulator run agrees on the plan, with minutes of human jitter.
     *
     * `day`  ≈ 8:00–4:00 with a 30-min lunch → ~7.5h/day and at most ~38.6h a
     *        week, so it stays under the 40h overtime line however jitter falls.
     * `long` ≈ 7:00–5:30 with a 30-min lunch → ~10h/day, ~50h a week: the one
     *        contractor whose work order runs into overtime.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public static function shiftsFor(string $shift, int $personId, CarbonImmutable $date, string $timezone): array
    {
        $day = $date->toDateString();
        if (CarbonImmutable::parse($day, $timezone)->isWeekend()) {
            return [];
        }

        $at = fn (string $time, int $min, int $max, string $salt): CarbonImmutable => CarbonImmutable::parse("{$day} {$time}", $timezone)
            ->addMinutes(self::jitter($personId, $day, $salt, $min, $max));

        [$start, $end] = $shift === 'long' ? ['07:00', '17:30'] : ['08:00', '16:00'];

        $in = $at($start, -8, 8, 'in');
        $lunchOut = $at('12:00', 0, 10, 'lunch');
        $lunchIn = $lunchOut->addMinutes(30 + self::jitter($personId, $day, 'back', 0, 4));
        $out = $at($end, -5, 5, 'out');

        return [[$in, $lunchOut], [$lunchIn, $out]];
    }

    private static function jitter(int $personId, string $day, string $salt, int $min, int $max): int
    {
        return $min + crc32("{$personId}|{$day}|{$salt}") % ($max - $min + 1);
    }
}
