<?php

namespace App\Domain\People\Models;

use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Inventory\Models\ContractorChargeSchedule;
use App\Domain\Inventory\Models\ContractorChargeScheduleEntry;
use App\Domain\People\Concerns\HasLegalHold;
use App\Domain\People\Enums\BackgroundCheckStatus;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyAssignment;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Shared\Models\File;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Notifications\NotificationCategory;
use Carbon\CarbonImmutable;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use RuntimeException;
use Spatie\Permission\Traits\HasRoles;

/**
 * Every human in the system — applicant, contractor, or W-2 staff — is one
 * `people` row, distinguished by {@see PersonStatus}. Roles (Spatie) are
 * independent of status. See ADR-0004 and 20-domain/people-lifecycle.md.
 *
 * @property PersonStatus $status
 * @property CarbonImmutable|null $application_date
 * @property CarbonImmutable|null $hire_date
 * @property CarbonImmutable|null $converted_to_contractor_at
 * @property CarbonImmutable|null $terminated_at
 * @property CarbonImmutable|null $dob
 * @property BackgroundCheckStatus|null $background_check_status
 * @property CarbonImmutable|null $background_check_completed_at
 * @property CarbonImmutable|null $i9_verified_at
 * @property array<int, string>|null $onboarding_waived_items
 * @property array<int, string>|null $muted_notifications
 */
class Person extends Authenticatable
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory, HasLegalHold, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $table = 'people';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'normalized_phone',
        'status',
        'application_date',
        'converted_to_contractor_at',
        'terminated_at',
        'hire_date',
        'primary_recruiter_id',
        'dob',
        'address',
        'apartment_number',
        'city',
        'state',
        'zip',
        'usa_citizen',
        'eligible_to_work',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'emergency_contact_address',
        'avatar_file_id',
        'muted_notifications',
        'id_front_file_id',
        'id_front_uploaded_at',
        'id_back_file_id',
        'id_back_uploaded_at',
        'i9_file_id',
        'i9_uploaded_at',
        'i9_verified_by',
        'i9_verified_at',
        'w9_file_id',
        'w9_uploaded_at',
        'contractor_agreement_file_id',
        'contractor_agreement_signed_at',
        'background_check_status',
        'background_check_completed_at',
        'onboarding_waived_items',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'status' => PersonStatus::class,
            'application_date' => 'date',
            'converted_to_contractor_at' => 'datetime',
            'terminated_at' => 'datetime',
            'hire_date' => 'date',
            'dob' => 'date',
            'usa_citizen' => 'boolean',
            'eligible_to_work' => 'boolean',
            'id_front_uploaded_at' => 'datetime',
            'id_back_uploaded_at' => 'datetime',
            'i9_uploaded_at' => 'datetime',
            'i9_verified_at' => 'datetime',
            'w9_uploaded_at' => 'datetime',
            'contractor_agreement_signed_at' => 'datetime',
            'background_check_status' => BackgroundCheckStatus::class,
            'background_check_completed_at' => 'datetime',
            'onboarding_waived_items' => 'array',
            'muted_notifications' => 'array',
            'legal_hold' => 'boolean',
            'legal_hold_set_at' => 'datetime',
            'is_anonymized' => 'boolean',
            'anonymized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // application_date is the immutable legal record of original application
        // (people-lifecycle.md). Once set, it never changes — even on rehire.
        static::updating(function (Person $person): void {
            if ($person->isDirty('application_date') && $person->getOriginal('application_date') !== null) {
                throw new RuntimeException('application_date is immutable once set.');
            }
        });
    }

    /**
     * The recruiter who "owns" this contractor (ADR-0019).
     *
     * @return BelongsTo<Person, $this>
     */
    public function primaryRecruiter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'primary_recruiter_id');
    }

    /**
     * Profile photo, stored as a private {@see File} and streamed through
     * an authenticated route — never a public storage URL.
     *
     * @return BelongsTo<File, $this>
     */
    public function avatarFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'avatar_file_id');
    }

    /** Whether this person opted out of a notification category (My Profile → Notifications). */
    public function hasMutedNotifications(NotificationCategory $category): bool
    {
        return in_array($category->value, $this->muted_notifications ?? [], true);
    }

    /** Authenticated avatar URL (versioned for cache busting), or null. */
    public function avatarUrl(): ?string
    {
        return $this->avatar_file_id === null
            ? null
            : "/admin/people/{$this->id}/avatar?v={$this->avatar_file_id}";
    }

    /**
     * @return HasMany<PropertyAssignment, $this>
     */
    public function propertyAssignments(): HasMany
    {
        return $this->hasMany(PropertyAssignment::class);
    }

    /**
     * Properties this person is assigned to (as recruiter or PM).
     *
     * @return BelongsToMany<Property, $this>
     */
    public function assignedProperties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_assignments')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Contractors a recruiter "owns" for roster-count purposes (ADR-0019) —
     * scoped by `primary_recruiter_id`, so temporary visitors (whose primary
     * recruiter is unchanged) never inflate the count.
     *
     * @param  Builder<Person>  $query
     */
    public function scopePrimaryContractorsOf(Builder $query, int $recruiterId): void
    {
        $query->where('primary_recruiter_id', $recruiterId)
            ->whereIn('status', [PersonStatus::ContractorActive, PersonStatus::ContractorInactive]);
    }

    /** Whether this person is assigned to the given property (any role). */
    public function isAssignedTo(Property $property): bool
    {
        return $this->propertyAssignments()
            ->where('property_id', $property->getKey())
            ->exists();
    }

    /**
     * Work orders where this person is the contractor.
     *
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Hotel-specific identifiers used to match this contractor on hour imports.
     *
     * @return HasMany<PersonExternalId, $this>
     */
    public function externalIds(): HasMany
    {
        return $this->hasMany(PersonExternalId::class);
    }

    /**
     * Job applications this person submitted (Phase 08b-i).
     *
     * @return HasMany<JobApplication, $this>
     */
    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Payroll adjustments (incentives/deductions) applied to this person.
     *
     * @return HasMany<TimeEntryAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(TimeEntryAdjustment::class);
    }

    /**
     * Contractor charge schedules (e.g. uniform deductions) for this person.
     *
     * @return HasMany<ContractorChargeSchedule, $this>
     */
    public function chargeSchedules(): HasMany
    {
        return $this->hasMany(ContractorChargeSchedule::class);
    }

    /** Total cents still scheduled (not yet applied) across active charge schedules. */
    public function outstandingChargeBalance(): int
    {
        return (int) ContractorChargeScheduleEntry::query()
            ->where('status', 'scheduled')
            ->whereHas('schedule', fn ($q) => $q->where('person_id', $this->id)->where('status', 'active'))
            ->sum('amount');
    }
}
