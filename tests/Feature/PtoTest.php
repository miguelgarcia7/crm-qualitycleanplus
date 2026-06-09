<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\Pto\Actions\AdjustPtoBalance;
use App\Domain\Pto\Actions\ApprovePtoRequest;
use App\Domain\Pto\Actions\EnsurePtoYear;
use App\Domain\Pto\Actions\ProcessPtoTenureCrossings;
use App\Domain\Pto\Actions\RejectPtoRequest;
use App\Domain\Pto\Actions\SubmitPtoRequest;
use App\Domain\Pto\Enums\PtoAllotmentStatus;
use App\Domain\Pto\Enums\PtoBucket;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Enums\PtoTier;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Pto\Models\PtoYearAllotment;
use App\Domain\Pto\Services\PtoTenure;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function staffMember(string $role, ?string $hireDate = null): Person
{
    $person = Person::factory()->create(['status' => PersonStatus::StaffActive, 'hire_date' => $hireDate]);
    $person->assignRole($role);

    return $person;
}

/** @return array<string, mixed> */
function ptoRequestData(string $bucket = 'vacation', float $hours = 10): array
{
    return [
        'bucket' => $bucket,
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
        'hours' => $hours,
    ];
}

it('computes the accrual tier from continuous tenure', function () {
    $tenure = app(PtoTenure::class);
    $today = CarbonImmutable::now()->startOfDay();

    expect($tenure->tierAsOf(staffMember('hr', $today->toDateString()), $today))->toBe(PtoTier::Probation)
        ->and($tenure->tierAsOf(staffMember('hr', $today->subDays(40)->toDateString()), $today))->toBe(PtoTier::Tier1)
        ->and($tenure->tierAsOf(staffMember('hr', $today->subMonths(7)->toDateString()), $today))->toBe(PtoTier::Tier2)
        ->and($tenure->tierAsOf(staffMember('hr', $today->subMonths(13)->toDateString()), $today))->toBe(PtoTier::Tier3);
});

it('deducts available balance on submission', function () {
    $person = staffMember('payroll', now()->subMonths(14)->toDateString());

    app(SubmitPtoRequest::class)->handle($person, ptoRequestData(hours: 10));

    $allotment = PtoYearAllotment::query()->where('person_id', $person->id)->firstOrFail();
    expect($allotment->allotmentFor(PtoBucket::Vacation))->toBe(40.0)
        ->and($allotment->availableFor(PtoBucket::Vacation))->toBe(30.0);
});

it('blocks a request that exceeds the available balance', function () {
    $person = staffMember('payroll', now()->subMonths(14)->toDateString());

    expect(fn () => app(SubmitPtoRequest::class)->handle($person, ptoRequestData(hours: 50)))
        ->toThrow(ValidationException::class);
});

it('blocks PTO for non-staff', function () {
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive, 'hire_date' => now()->subYear()->toDateString()]);

    expect(fn () => app(SubmitPtoRequest::class)->handle($contractor, ptoRequestData()))
        ->toThrow(ValidationException::class);
});

it('blocks HR from approving their own request but lets admin self-approve', function () {
    $hr = staffMember('hr', now()->subMonths(14)->toDateString());
    $admin = staffMember('admin', now()->subMonths(14)->toDateString());

    $hrReq = app(SubmitPtoRequest::class)->handle($hr, ptoRequestData());
    $adminReq = app(SubmitPtoRequest::class)->handle($admin, ptoRequestData());

    expect($hr->can('approve', $hrReq))->toBeFalse()       // HR self-approval guardrail
        ->and($admin->can('approve', $adminReq))->toBeTrue();

    app(ApprovePtoRequest::class)->handle($adminReq, $admin);
    expect($adminReq->fresh()->status)->toBe(PtoRequestStatus::Approved)
        ->and($adminReq->fresh()->is_self_approved)->toBeTrue();
});

it('returns hours when a request is rejected or cancelled', function () {
    $person = staffMember('payroll', now()->subMonths(14)->toDateString());
    $admin = staffMember('admin', now()->subMonths(14)->toDateString());

    $req = app(SubmitPtoRequest::class)->handle($person, ptoRequestData(hours: 10));
    $allotment = PtoYearAllotment::query()->where('person_id', $person->id)->firstOrFail();
    expect($allotment->availableFor(PtoBucket::Vacation))->toBe(30.0);

    app(RejectPtoRequest::class)->handle($req, $admin, 'No coverage');
    expect($allotment->availableFor(PtoBucket::Vacation))->toBe(40.0);
});

it('applies a manual balance adjustment', function () {
    $person = staffMember('payroll', now()->subMonths(14)->toDateString());
    $admin = staffMember('admin', now()->subMonths(14)->toDateString());
    app(EnsurePtoYear::class)->handle($person);

    app(AdjustPtoBalance::class)->handle($person, ['vacation_hours' => 5, 'reason' => 'Goodwill'], $admin);

    $allotment = PtoYearAllotment::query()->where('person_id', $person->id)->firstOrFail();
    expect($allotment->allotmentFor(PtoBucket::Vacation))->toBe(45.0);
});

it('tops up by the delta on a tier-crossing day (idempotent)', function () {
    $hire = CarbonImmutable::now()->startOfDay()->subDays(30);
    $person = staffMember('recruiter', $hire->toDateString());
    // Pre-existing allotment created at hire (probation 0s).
    app(EnsurePtoYear::class)->handle($person, $hire);

    app(ProcessPtoTenureCrossings::class)->handle(CarbonImmutable::now()->startOfDay());
    $allotment = PtoYearAllotment::query()->where('person_id', $person->id)->firstOrFail();
    expect($allotment->allotmentFor(PtoBucket::Vacation))->toBe(20.0);

    // Rerun same day → no double top-up.
    app(ProcessPtoTenureCrossings::class)->handle(CarbonImmutable::now()->startOfDay());
    expect($allotment->fresh()->allotmentFor(PtoBucket::Vacation))->toBe(20.0);
});

it('forfeits and refreshes on the hire anniversary', function () {
    $today = CarbonImmutable::now()->startOfDay();
    $hire = $today->subYear();
    $person = staffMember('hr', $hire->toDateString());
    PtoYearAllotment::factory()->create([
        'person_id' => $person->id, 'year_start' => $hire->toDateString(), 'year_end' => $today->toDateString(),
        'tier_at_year_start' => PtoTier::Tier3->value, 'vacation_allotment' => 40, 'scheduled_allotment' => 40,
        'unscheduled_allotment' => 40, 'status' => PtoAllotmentStatus::Open,
    ]);

    app(ProcessPtoTenureCrossings::class)->handle($today);

    $prior = PtoYearAllotment::query()->where('person_id', $person->id)->whereDate('year_start', $hire->toDateString())->firstOrFail();
    $current = PtoYearAllotment::query()->where('person_id', $person->id)->whereDate('year_start', $today->toDateString())->firstOrFail();
    expect($prior->status)->toBe(PtoAllotmentStatus::Forfeited)
        ->and($current->status)->toBe(PtoAllotmentStatus::Open)
        ->and($current->allotmentFor(PtoBucket::Vacation))->toBe(40.0);
});

it('lets a staff member load the Time Off page and submit via HTTP', function () {
    $person = staffMember('hr', now()->subMonths(14)->toDateString());

    $this->actingAs($person)->get(main('/admin/pto'))->assertOk();

    $this->actingAs($person)->post(main('/admin/pto'), ptoRequestData(hours: 8))->assertRedirect();
    expect(PtoRequest::query()->where('person_id', $person->id)->count())->toBe(1);
});
