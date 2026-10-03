<?php

namespace Database\Seeders;

use App\Domain\Demo\Actions\AdvanceDemoBilling;
use App\Domain\Demo\Actions\SimulateClock;
use App\Domain\Demo\Concerns\TravelsInTime;
use App\Domain\Demo\DemoRoster;
use App\Domain\Devices\Models\Device;
use App\Domain\People\Enums\BackgroundCheckStatus;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Models\Holiday;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Pto\Actions\EnsurePtoYear;
use App\Domain\Recruiting\Actions\SubmitApplication;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Enums\JobPostingStatus;
use App\Domain\Recruiting\Models\JobPosting;
use App\Domain\WorkOrders\Actions\CreateWorkOrder;
use App\Domain\WorkOrders\Actions\SupersedeWorkOrder;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * The demo (docs/80-plan/demo-environment.md): one login per role, the
 * DemoRoster companies with their Bible rate history, contractors on work
 * orders (some with a pay raise partway through), and six weeks of history
 * replayed through the same simulator that keeps the demo live — real clock
 * punches, then the billing cadence that leaves last week with each PM and
 * older weeks invoiced, sent and paid. Plus job postings and applicants.
 *
 * Runs on the structural seeders (roles, positions, holidays) via demo:reset,
 * and avoids factories — Faker isn't installed on Cloud builds.
 *
 * @phpstan-import-type DemoProperty from DemoRoster
 */
class DemoSeeder extends Seeder
{
    use TravelsInTime;

    private const HISTORY_WEEKS = 6;

    /** @var array<string, Person> email => login */
    private array $people = [];

    public function run(): void
    {
        $now = CarbonImmutable::now();
        $password = Hash::make((string) config('demo.password'));

        foreach (DemoRoster::STAFF as $email => [$role, $name, $monthsEmployed]) {
            $person = Person::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'status' => PersonStatus::StaffActive,
                'hire_date' => $monthsEmployed !== null ? $now->subMonths($monthsEmployed)->toDateString() : null,
                'email_verified_at' => $now,
            ]);
            $person->syncRoles($role);
            $this->people[$email] = $person;

            if ($monthsEmployed !== null) {
                app(EnsurePtoYear::class)->handle($person);
            }
        }

        $positions = Position::query()->get()->keyBy('slug');
        $properties = [];
        $raises = [];

        foreach (DemoRoster::PROPERTIES as $p) {
            $property = $this->createProperty($p, $positions, $now);
            $properties[$p['name']] = $property;
            $recruiter = $this->people[$p['recruiter']];
            $currentRates = $p['rate_history'][array_key_last($p['rate_history'])]['rates'];

            foreach ($p['contractors'] as $c) {
                $startDate = $now->subWeeks($c['started_weeks_ago']);
                $contractor = Person::create([
                    'name' => $c['name'],
                    'email' => $c['email'],
                    'password' => $password,
                    'phone' => $c['phone'],
                    'normalized_phone' => preg_replace('/\D/', '', $c['phone']),
                    'status' => PersonStatus::ContractorActive,
                    'primary_recruiter_id' => $recruiter->id,
                    'converted_to_contractor_at' => $startDate,
                    'email_verified_at' => $now,
                ]);
                $contractor->syncRoles('contractor');

                $workOrder = app(CreateWorkOrder::class)->handle([
                    'person_id' => $contractor->id,
                    'property_id' => $property->id,
                    'position_id' => $positions[$c['position']]->id,
                    ...$this->rates($c['rate'] ?? $currentRates[$c['position']]),
                    'start_date' => $startDate->toDateString(),
                    'status' => WorkOrderStatus::Active,
                ], $recruiter);

                if ($c['raise'] !== null) {
                    $raises[] = [
                        'effective' => $property->weekStartFor($now->setTimezone($property->timezone))->subWeeks($c['raise']['weeks_ago']),
                        'work_order' => $workOrder,
                        'rate' => $c['raise']['rate'],
                        'recruiter' => $recruiter,
                    ];
                }
            }

            if ($p['tablet'] !== null) {
                // The kiosk the tablet contractors punch on, ready to pair a
                // real tablet with this code.
                Device::create([
                    'property_id' => $property->id,
                    'name' => $p['tablet'][0],
                    'activation_code' => $p['tablet'][1],
                    'created_by' => $this->people['admin@example.com']->id,
                ]);
            }
        }

        // Six finished weeks plus this week so far — the same code the schedule
        // runs — pausing at each pay raise to supersede the work order, so the
        // weeks before it are worked (and billed) at the old rate.
        usort($raises, fn (array $a, array $b) => $a['effective'] <=> $b['effective']);
        foreach ($raises as $raise) {
            $this->replay($properties, $raise['effective']->subSecond(), $now);
            // Approved the Friday before it took effect.
            $this->at($raise['effective']->subDays(3)->setTime(14, 0), fn () => app(SupersedeWorkOrder::class)->handle(
                WorkOrder::query()->findOrFail($raise['work_order']->id),
                $this->rates($raise['rate']),
                WorkOrderSource::PayIncrease,
                $raise['effective'],
                $raise['recruiter'],
            ));
        }
        $this->replay($properties, $now, $now);

        foreach ($properties as $property) {
            app(AdvanceDemoBilling::class)->handle($property, $now);
        }

        $this->seedRecruiting($properties, $now);

        Artisan::call('payroll:ensure-periods');
    }

    /**
     * @param  DemoProperty  $p
     * @param  Collection<string, Position>  $positions
     */
    private function createProperty(array $p, Collection $positions, CarbonImmutable $now): Property
    {
        $recruiter = $this->people[$p['recruiter']];
        $pm = $this->people[$p['pm']];

        $property = Property::create([
            'name' => $p['name'],
            'address' => $p['address'],
            'city' => $p['city'],
            'state' => $p['state'],
            'zip' => $p['zip'],
            'main_phone' => $p['phone'],
            'pm_name' => $pm->name,
            'pm_phone' => $p['pm_phone'],
            'billing_email' => $p['email'],
            'timezone' => 'America/Chicago',
            'latitude' => $p['latitude'],   // QR clock-in geofence
            'longitude' => $p['longitude'],
            'qr_clock_enabled' => true,
            'tax_rate' => $p['tax_rate'],
            'closing_day' => 7, // weeks run Monday–Sunday
            'status' => PropertyStatus::Active,
            'created_by' => $this->people['admin@example.com']->id,
        ]);
        $property->holidays()->sync(Holiday::query()->whereIn('slug', Holiday::DEFAULT_ENABLED_SLUGS)->pluck('id'));

        $property->assignments()->create(['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value]);
        $property->assignments()->create(['person_id' => $pm->id, 'role' => PropertyAssignmentRole::PropertyManager->value]);

        // Bible rates are append-only: each change closes the previous row the
        // day before the new one takes effect.
        $history = $p['rate_history'];
        foreach ($history as $i => $entry) {
            $effective = $now->subMonths($entry['months_ago']);
            $next = $history[$i + 1] ?? null;

            foreach ($entry['rates'] as $slug => $rate) {
                $property->positionRates()->create([
                    'position_id' => $positions[$slug]->id,
                    ...$this->rates($rate),
                    'effective_date' => $effective->toDateString(),
                    'end_date' => $next !== null ? $now->subMonths($next['months_ago'])->subDay()->toDateString() : null,
                    'is_active' => true,
                    'created_by' => $recruiter->id,
                ]);
            }
        }

        return $property;
    }

    /**
     * Plays every company's punches up to $until, from six weeks before this
     * week. Idempotent, so each pause only adds what's new since the last.
     *
     * @param  array<string, Property>  $properties
     */
    private function replay(array $properties, CarbonImmutable $until, CarbonImmutable $now): void
    {
        foreach ($properties as $property) {
            $from = $property->weekStartFor($now->setTimezone($property->timezone))->subWeeks(self::HISTORY_WEEKS);
            app(SimulateClock::class)->handle($property, $from, $until);
        }
    }

    /**
     * @param  array{0: int, 1: int}  $rate  cents/hour: [pay, bill]
     * @return array{pay_rate: int, bill_rate: int, ot_pay_rate: int, ot_bill_rate: int}
     */
    private function rates(array $rate): array
    {
        [$pay, $bill] = $rate;

        return [
            'pay_rate' => $pay,
            'bill_rate' => $bill,
            'ot_pay_rate' => (int) round($pay * 1.5),
            'ot_bill_rate' => (int) round($bill * 1.5),
        ];
    }

    /**
     * Three job postings (two on the public board, one draft) and three
     * applicants at different stages, submitted through the real public path
     * so each becomes an applicant Person.
     *
     * @param  array<string, Property>  $properties
     */
    private function seedRecruiting(array $properties, CarbonImmutable $now): void
    {
        $rita = $this->people['recruiter@example.com'];
        $ray = $this->people['recruiter2@example.com'];

        $housekeeper = $this->at($now->subWeeks(3), fn () => JobPosting::create([
            'status' => JobPostingStatus::Published,
            'title' => 'Housekeeper',
            'slug' => 'housekeeper-acme-hotel',
            'pay_range' => '$16 - $17 / hr',
            'content' => 'Clean and refresh guest rooms at a downtown Chicago hotel. Monday–Friday days, no experience needed.',
            'hour_start' => '08:00',
            'hour_end' => '16:00',
            'property_id' => $properties['Acme Hotel']->id,
            'created_by' => $rita->id,
        ]));

        $janitor = $this->at($now->subWeeks(2), fn () => JobPosting::create([
            'status' => JobPostingStatus::Published,
            'title' => 'Janitor',
            'slug' => 'janitor-qcp-property',
            'pay_range' => '$15 - $16 / hr',
            'content' => 'Keep offices, hallways and restrooms clean at a Dallas office building. Monday–Friday days.',
            'hour_start' => '08:00',
            'hour_end' => '16:00',
            'property_id' => $properties['QCP Property']->id,
            'created_by' => $ray->id,
        ]));

        $this->at($now->subDays(2), fn () => JobPosting::create([
            'status' => JobPostingStatus::Draft,
            'title' => 'Line Cook',
            'slug' => 'line-cook-mag-solutions',
            'pay_range' => '$18 - $19 / hr',
            'content' => 'Prep and cook for a busy Carrollton kitchen. (Draft — not published yet.)',
            'hour_start' => '07:00',
            'hour_end' => '15:00',
            'property_id' => $properties['MAG Solutions']->id,
            'created_by' => $ray->id,
        ]));

        $applicant = fn (array $data, JobPosting $posting, CarbonImmutable $at) => $this->at($at, fn () => app(SubmitApplication::class)->handle([
            'transportation' => '1', 'work_at_qcp' => '0', 'usa_citizen' => '1',
            'another_staff_agency' => '0', 'convicted_felon' => '0', 'acknowledgement' => '1',
            'position' => $posting->title,
            'start_date' => $at->addWeeks(2)->toDateString(),
            ...$data,
        ], $posting));

        // New — waiting for someone to look at it.
        $applicant([
            'first_name' => 'Elena', 'last_name' => 'Torres', 'email' => 'elena.torres@example.com',
            'phone' => '(312) 555-0171', 'address' => '1450 W Madison St', 'city' => 'Chicago',
            'state' => 'IL', 'zip' => '60607', 'desired_salary' => '17', 'dob' => '1994-05-18',
            'full_name' => 'Rosa Torres', 'emergency_phone' => '(312) 555-0181', 'relationship' => 'Mother',
        ], $housekeeper, $now->subDay());

        // Under review: background check ordered, onboarding documents pending.
        $marcus = $applicant([
            'first_name' => 'Marcus', 'last_name' => 'Lee', 'email' => 'marcus.lee@example.com',
            'phone' => '(214) 555-0172', 'address' => '3100 Inwood Rd', 'city' => 'Dallas',
            'state' => 'TX', 'zip' => '75235', 'desired_salary' => '16', 'dob' => '1990-11-02',
            'full_name' => 'Dana Lee', 'emergency_phone' => '(214) 555-0182', 'relationship' => 'Spouse',
        ], $janitor, $now->subDays(6));
        $marcus->update(['status' => JobApplicationStatus::Reviewing, 'reviewed_by' => $ray->id, 'reviewed_at' => $now->subDays(4)]);
        $marcus->person->forceFill(['background_check_status' => BackgroundCheckStatus::Pending])->save();

        // Turned down.
        $nina = $applicant([
            'first_name' => 'Nina', 'last_name' => 'Patel', 'email' => 'nina.patel@example.com',
            'phone' => '(972) 555-0173', 'address' => '2201 E Belt Line Rd', 'city' => 'Carrollton',
            'state' => 'TX', 'zip' => '75006', 'desired_salary' => '19', 'dob' => '1998-02-27',
            'transportation' => '0',
        ], $janitor, $now->subDays(10));
        $nina->update([
            'status' => JobApplicationStatus::Rejected,
            'reviewed_by' => $ray->id,
            'reviewed_at' => $now->subDays(8),
            'rejected_reason' => 'Needs evening hours only; the opening is a day shift.',
        ]);
    }
}
