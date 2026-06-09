<?php

namespace App\Domain\Recruiting\Actions;

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Recruiting\Models\JobPosting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records a public job application (Phase 08b-i): durable identity/contact facts
 * land on a {@see Person} (status=applicant), the application event + at-the-time
 * legal attestations land on a {@see JobApplication}. One identity per human —
 * an application whose email matches an existing person links to that record
 * rather than creating a duplicate (and never overwrites their existing data).
 */
class SubmitApplication
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?JobPosting $posting = null): JobApplication
    {
        return DB::transaction(function () use ($data, $posting) {
            $person = $this->resolvePerson($data);

            return JobApplication::create([
                'person_id' => $person->id,
                'job_posting_id' => $posting?->id,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'second_last_name' => $data['second_last_name'] ?? null,
                'desired_position' => $data['position'] ?? $posting?->title,
                'desired_salary' => $data['desired_salary'] ?? null,
                'desired_start_date' => $data['start_date'] ?? null,
                'transportation' => $this->toBool($data['transportation'] ?? null),
                'work_at_qcp' => $this->toBool($data['work_at_qcp'] ?? null),
                'work_at_qcp_explain' => $data['work_at_qcp_explain'] ?? null,
                'another_staff_agency' => $this->toBool($data['another_staff_agency'] ?? null),
                'non_complete' => $data['non_complete'] ?? null,
                'convicted_felon' => $this->toBool($data['convicted_felon'] ?? null),
                'felony_conviction' => $data['felony_conviction'] ?? null,
                'acknowledgement' => $this->toBool($data['acknowledgement'] ?? null),
                'status' => JobApplicationStatus::Submitted,
                'submitted_at' => CarbonImmutable::now(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePerson(array $data): Person
    {
        $email = isset($data['email']) && $data['email'] !== '' ? (string) $data['email'] : null;

        if ($email !== null) {
            $existing = Person::query()->where('email', $email)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $phone = isset($data['phone']) && $data['phone'] !== '' ? (string) $data['phone'] : null;

        return Person::create([
            'name' => trim(((string) ($data['first_name'] ?? '')).' '.((string) ($data['last_name'] ?? ''))),
            'email' => $email ?? sprintf('applicant+%s@qcp.invalid', Str::lower(Str::random(20))),
            'phone' => $phone,
            'normalized_phone' => $phone === null ? null : preg_replace('/\D/', '', $phone),
            'status' => PersonStatus::Applicant,
            'application_date' => CarbonImmutable::now()->toDateString(),
            'dob' => $data['dob'] ?? null,
            'address' => $data['address'] ?? null,
            'apartment_number' => $data['apartment_number'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip' => $data['zip'] ?? null,
            'usa_citizen' => $this->toBool($data['usa_citizen'] ?? null),
            'eligible_to_work' => $this->toBool($data['eligible_to_work'] ?? null),
            'emergency_contact_name' => $data['full_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_phone'] ?? null,
            'emergency_contact_relationship' => $data['relationship'] ?? null,
            'emergency_contact_address' => $data['full_address'] ?? null,
        ]);
    }

    private function toBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value === true || $value === 1 || $value === '1';
    }
}
