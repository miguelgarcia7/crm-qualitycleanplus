<?php

namespace App\Domain\Demo;

use Carbon\CarbonImmutable;

/**
 * The demo cast (docs/80-plan/demo-environment.md): one login per role (two
 * recruiters, a manager per company), three companies with their Bible rate
 * history, their contractors, and each contractor's daily shift plan. The
 * single source for DemoSeeder (who exists) and SimulateClock (when they
 * punch).
 *
 * @phpstan-type DemoRate array{0: int, 1: int}
 * @phpstan-type DemoContractor array{name: string, email: string, phone: string, position: string, rate: DemoRate|null, raise: array{weeks_ago: int, rate: DemoRate}|null, shift: string, method: string, started_weeks_ago: int}
 * @phpstan-type DemoPlacedContractor array{name: string, email: string, phone: string, position: string, rate: DemoRate|null, raise: array{weeks_ago: int, rate: DemoRate}|null, shift: string, method: string, started_weeks_ago: int, property: string}
 * @phpstan-type DemoProperty array{name: string, address: string, city: string, state: string, zip: string, phone: string, email: string, latitude: float, longitude: float, tax_rate: float, recruiter: string, pm: string, pm_phone: string, tablet: array{0: string, 1: string}|null, rate_history: list<array{months_ago: int, rates: array<string, DemoRate>}>, contractors: list<DemoContractor>}
 */
final class DemoRoster
{
    /**
     * Every non-contractor login: email => [role, name, months employed].
     * Property managers are the companies' own people, not QCP staff, so they
     * have no hire date.
     *
     * @var array<string, array{0: string, 1: string, 2: int|null}>
     */
    public const STAFF = [
        'super-admin@example.com' => ['super_admin', 'Sam Super', 48],
        'admin@example.com' => ['admin', 'Ada Admin', 40],
        'office-manager@example.com' => ['office_manager', 'Olivia Office', 30],
        'front-desk@example.com' => ['front_desk', 'Frank Desk', 4],
        'hr@example.com' => ['hr', 'Hannah HR', 26],
        'payroll@example.com' => ['payroll', 'Paul Payroll', 14],
        'recruiter@example.com' => ['recruiter', 'Rita Recruiter', 18],
        'recruiter2@example.com' => ['recruiter', 'Ray Recruiter', 7],
        'w2@example.com' => ['w2_employee', 'Wendy Worker', 9],
        'pm@example.com' => ['property_manager', 'Pat Manager', null],
        'pm.qcp@example.com' => ['property_manager', 'Quinn Porter', null],
        'pm.mag@example.com' => ['property_manager', 'Megan Grant', null],
    ];

    /**
     * The demo companies. `rate_history` is each one's Bible rates (cents/hour:
     * position slug => [pay, bill], OT 1.5×), oldest first — the last entry is
     * the current rate. A contractor's `rate` is what their work order started
     * at (null: the current Bible rate); `raise` supersedes that work order
     * with a pay increase on the Monday `weeks_ago` weeks back. `shift` picks
     * the plan in shiftsFor(); `method` is how they punch — QR on their phone
     * or the property's tablet.
     *
     * @var list<DemoProperty>
     */
    public const PROPERTIES = [
        [
            'name' => 'Acme Hotel',
            'address' => '233 N Michigan Ave',
            'city' => 'Chicago',
            'state' => 'IL',
            'zip' => '60601',
            'phone' => '(312) 555-0100',
            'email' => 'accounts.payable@example.com',
            'latitude' => 41.8865,
            'longitude' => -87.6244,
            'tax_rate' => 0.0625,
            'recruiter' => 'recruiter@example.com',
            'pm' => 'pm@example.com',
            'pm_phone' => '(312) 555-0101',
            'tablet' => ['Lobby Tablet', 'ACME01'],
            'rate_history' => [
                ['months_ago' => 6, 'rates' => [
                    'housekeeper' => [1600, 2600],
                    'houseman' => [1700, 2750],
                    'laundry-attendant' => [1550, 2500],
                    'public-space-attendant' => [1650, 2650],
                    'banquet-server' => [1800, 2950],
                ]],
            ],
            'contractors' => [
                ['name' => 'Maria Lopez', 'email' => 'maria.lopez@example.com', 'phone' => '(312) 555-0141', 'position' => 'housekeeper', 'rate' => null, 'raise' => null, 'shift' => 'day', 'method' => 'qr', 'started_weeks_ago' => 22],
                ['name' => 'James Carter', 'email' => 'james.carter@example.com', 'phone' => '(312) 555-0142', 'position' => 'houseman', 'rate' => [1850, 2950], 'raise' => null, 'shift' => 'long', 'method' => 'qr', 'started_weeks_ago' => 17],
                ['name' => 'Aisha Brown', 'email' => 'aisha.brown@example.com', 'phone' => '(312) 555-0143', 'position' => 'laundry-attendant', 'rate' => null, 'raise' => null, 'shift' => 'day', 'method' => 'tablet', 'started_weeks_ago' => 13],
                ['name' => 'Tom Nguyen', 'email' => 'tom.nguyen@example.com', 'phone' => '(312) 555-0144', 'position' => 'public-space-attendant', 'rate' => [1700, 2700], 'raise' => null, 'shift' => 'day', 'method' => 'tablet', 'started_weeks_ago' => 9],
                ['name' => 'Sofia Ramirez', 'email' => 'sofia.ramirez@example.com', 'phone' => '(312) 555-0145', 'position' => 'banquet-server', 'rate' => null, 'raise' => null, 'shift' => 'day', 'method' => 'qr', 'started_weeks_ago' => 3],
            ],
        ],
        [
            'name' => 'QCP Property',
            'address' => '1720 Regal Row',
            'city' => 'Dallas',
            'state' => 'TX',
            'zip' => '75235',
            'phone' => '(214) 333-4444',
            'email' => 'qcp@example.com',
            'latitude' => 32.8197,
            'longitude' => -96.8710,
            'tax_rate' => 0.0825,
            'recruiter' => 'recruiter2@example.com',
            'pm' => 'pm.qcp@example.com',
            'pm_phone' => '(214) 333-4445',
            'tablet' => null,
            'rate_history' => [
                ['months_ago' => 14, 'rates' => ['janitor' => [1400, 2250], 'housekeeper' => [1450, 2300]]],
                ['months_ago' => 4, 'rates' => ['janitor' => [1500, 2400], 'housekeeper' => [1550, 2450]]],
            ],
            'contractors' => [
                // Hired on the old Bible rate; a raise four weeks ago.
                ['name' => 'Carlos Mendez', 'email' => 'carlos.mendez@example.com', 'phone' => '(214) 555-0151', 'position' => 'janitor', 'rate' => [1400, 2250], 'raise' => ['weeks_ago' => 4, 'rate' => [1550, 2475]], 'shift' => 'day', 'method' => 'qr', 'started_weeks_ago' => 30],
                ['name' => 'Linda Park', 'email' => 'linda.park@example.com', 'phone' => '(214) 555-0152', 'position' => 'housekeeper', 'rate' => null, 'raise' => ['weeks_ago' => 2, 'rate' => [1625, 2575]], 'shift' => 'day', 'method' => 'qr', 'started_weeks_ago' => 12],
            ],
        ],
        [
            'name' => 'MAG Solutions',
            'address' => '1519 Palisaded Dr',
            'city' => 'Carrollton',
            'state' => 'TX',
            'zip' => '75007',
            'phone' => '(214) 333-5555',
            'email' => 'mag@example.com',
            'latitude' => 32.9927,
            'longitude' => -96.8807,
            'tax_rate' => 0.0825,
            'recruiter' => 'recruiter2@example.com',
            'pm' => 'pm.mag@example.com',
            'pm_phone' => '(214) 333-5556',
            'tablet' => ['Front Office Tablet', 'MAG001'],
            'rate_history' => [
                ['months_ago' => 12, 'rates' => ['cook' => [1700, 2750], 'dishwasher' => [1350, 2200]]],
                ['months_ago' => 3, 'rates' => ['cook' => [1800, 2900], 'dishwasher' => [1450, 2350]]],
            ],
            'contractors' => [
                ['name' => 'Andre Wilson', 'email' => 'andre.wilson@example.com', 'phone' => '(972) 555-0161', 'position' => 'cook', 'rate' => [1700, 2750], 'raise' => ['weeks_ago' => 5, 'rate' => [1850, 2975]], 'shift' => 'day', 'method' => 'qr', 'started_weeks_ago' => 26],
                ['name' => 'Grace Kim', 'email' => 'grace.kim@example.com', 'phone' => '(972) 555-0162', 'position' => 'dishwasher', 'rate' => null, 'raise' => ['weeks_ago' => 3, 'rate' => [1525, 2450]], 'shift' => 'day', 'method' => 'tablet', 'started_weeks_ago' => 15],
            ],
        ],
    ];

    /**
     * Every contractor, with the company they work at.
     *
     * @return list<DemoPlacedContractor>
     */
    public static function contractors(): array
    {
        $all = [];
        foreach (self::PROPERTIES as $property) {
            foreach ($property['contractors'] as $contractor) {
                $all[] = [...$contractor, 'property' => $property['name']];
            }
        }

        return $all;
    }

    /** @return DemoPlacedContractor|null */
    public static function contractor(string $email): ?array
    {
        foreach (self::contractors() as $contractor) {
            if ($contractor['email'] === $email) {
                return $contractor;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function propertyNames(): array
    {
        return array_column(self::PROPERTIES, 'name');
    }

    /**
     * The companies a staff login looks after, as their recruiter or manager.
     *
     * @return list<string>
     */
    public static function propertiesFor(string $email): array
    {
        return array_values(array_map(
            fn (array $property) => $property['name'],
            array_filter(self::PROPERTIES, fn (array $property) => in_array($email, [$property['recruiter'], $property['pm']], true)),
        ));
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
