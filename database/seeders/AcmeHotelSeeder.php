<?php

namespace Database\Seeders;

use App\Domain\Demo\Actions\AdvanceDemoBilling;
use App\Domain\Demo\Actions\SimulateClock;
use App\Domain\Demo\DemoRoster;
use App\Domain\Devices\Models\Device;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Models\Holiday;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Pto\Actions\EnsurePtoYear;
use App\Domain\WorkOrders\Actions\CreateWorkOrder;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * The demo company (docs/80-plan/demo-environment.md): one login per role,
 * Acme Hotel with Bible rates, five contractors on work orders, and six weeks
 * of history replayed through the same simulator that keeps the demo live —
 * real clock punches, then the billing cadence that leaves last week with the
 * PM and older weeks invoiced, sent and paid.
 *
 * Runs on the structural seeders (roles, positions, holidays) via demo:reset,
 * and avoids factories — Faker isn't installed on Cloud builds.
 */
class AcmeHotelSeeder extends Seeder
{
    private const HISTORY_WEEKS = 6;

    public function run(): void
    {
        $now = CarbonImmutable::now();
        $password = Hash::make((string) config('demo.password'));

        $staff = [];
        foreach (DemoRoster::STAFF as $role => [$email, $name, $monthsEmployed]) {
            $staff[$role] = Person::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'status' => PersonStatus::StaffActive,
                'hire_date' => $monthsEmployed !== null ? $now->subMonths($monthsEmployed)->toDateString() : null,
                'email_verified_at' => $now,
            ]);
            $staff[$role]->syncRoles($role);

            if ($monthsEmployed !== null) {
                app(EnsurePtoYear::class)->handle($staff[$role]);
            }
        }

        $property = Property::create([
            'name' => DemoRoster::PROPERTY,
            'address' => '233 N Michigan Ave',
            'city' => 'Chicago',
            'state' => 'IL',
            'zip' => '60601',
            'main_phone' => '(312) 555-0100',
            'pm_name' => $staff['property_manager']->name,
            'pm_phone' => '(312) 555-0101',
            'billing_email' => 'accounts.payable@example.com',
            'timezone' => 'America/Chicago',
            'latitude' => 41.8865,   // QR clock-in geofence
            'longitude' => -87.6244,
            'qr_clock_enabled' => true,
            'tax_rate' => 0.0625,
            'closing_day' => 7, // weeks run Monday–Sunday
            'status' => PropertyStatus::Active,
            'created_by' => $staff['admin']->id,
        ]);
        $property->holidays()->sync(Holiday::query()->whereIn('slug', Holiday::DEFAULT_ENABLED_SLUGS)->pluck('id'));

        $recruiter = $staff['recruiter'];
        foreach (['recruiter' => PropertyAssignmentRole::Recruiter, 'property_manager' => PropertyAssignmentRole::PropertyManager] as $role => $assignment) {
            $property->assignments()->create(['person_id' => $staff[$role]->id, 'role' => $assignment->value]);
        }

        $positions = Position::query()->whereIn('slug', array_keys(DemoRoster::RATES))->get()->keyBy('slug');
        foreach (DemoRoster::RATES as $slug => [$pay, $bill]) {
            $property->positionRates()->create([
                'position_id' => $positions[$slug]->id,
                'pay_rate' => $pay,
                'bill_rate' => $bill,
                'ot_pay_rate' => (int) round($pay * 1.5),
                'ot_bill_rate' => (int) round($bill * 1.5),
                'effective_date' => $now->subMonths(6)->toDateString(),
                'is_active' => true,
                'created_by' => $recruiter->id,
            ]);
        }

        foreach (DemoRoster::CONTRACTORS as $c) {
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

            [$pay, $bill] = $c['rate'] ?? DemoRoster::RATES[$c['position']];
            app(CreateWorkOrder::class)->handle([
                'person_id' => $contractor->id,
                'property_id' => $property->id,
                'position_id' => $positions[$c['position']]->id,
                'pay_rate' => $pay,
                'bill_rate' => $bill,
                'ot_pay_rate' => (int) round($pay * 1.5),
                'ot_bill_rate' => (int) round($bill * 1.5),
                'start_date' => $startDate->toDateString(),
                'status' => WorkOrderStatus::Active,
            ], $recruiter);
        }

        // The lobby kiosk the tablet contractors punch on, ready to pair a real
        // tablet with this code.
        Device::create([
            'property_id' => $property->id,
            'name' => 'Lobby Tablet',
            'activation_code' => 'ACME01',
            'created_by' => $staff['admin']->id,
        ]);

        // Six finished weeks plus this week so far, then the billing steps
        // that have come due across them — the same code the schedule runs.
        $from = $property->weekStartFor($now->setTimezone($property->timezone))->subWeeks(self::HISTORY_WEEKS);
        app(SimulateClock::class)->handle($property, $from, $now);
        app(AdvanceDemoBilling::class)->handle($property, $now);

        Artisan::call('payroll:ensure-periods');
    }
}
