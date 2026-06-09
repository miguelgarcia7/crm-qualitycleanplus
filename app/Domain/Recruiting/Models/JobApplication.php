<?php

namespace App\Domain\Recruiting\Models;

use App\Domain\People\Models\Person;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\JobApplicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One public application submission (Phase 08b-i) — the immutable legal record of
 * what an applicant declared, when, and to which posting. Durable identity facts
 * live on the linked {@see Person}.
 *
 * @property JobApplicationStatus $status
 * @property CarbonImmutable $submitted_at
 * @property CarbonImmutable|null $desired_start_date
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $promoted_at
 */
class JobApplication extends Model
{
    /** @use HasFactory<JobApplicationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'person_id',
        'job_posting_id',
        'first_name',
        'middle_name',
        'last_name',
        'second_last_name',
        'desired_position',
        'desired_salary',
        'desired_start_date',
        'transportation',
        'work_at_qcp',
        'work_at_qcp_explain',
        'another_staff_agency',
        'non_complete',
        'convicted_felon',
        'felony_conviction',
        'acknowledgement',
        'status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'rejected_reason',
        'promoted_by',
        'promoted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobApplicationStatus::class,
            'desired_start_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'promoted_at' => 'datetime',
            'transportation' => 'boolean',
            'work_at_qcp' => 'boolean',
            'another_staff_agency' => 'boolean',
            'convicted_felon' => 'boolean',
            'acknowledgement' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'reviewed_by');
    }

    /**
     * @param  Builder<JobApplication>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereIn('status', [
            JobApplicationStatus::Submitted->value,
            JobApplicationStatus::Reviewing->value,
        ]);
    }
}
