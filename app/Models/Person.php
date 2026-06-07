<?php

namespace App\Models;

use App\Enums\PersonStatus;
use App\Models\Concerns\HasLegalHold;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** The recruiter who "owns" this contractor (ADR-0019). */
    public function primaryRecruiter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'primary_recruiter_id');
    }
}
