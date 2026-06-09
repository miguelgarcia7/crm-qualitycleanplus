<?php

namespace App\Domain\Recruiting\Models;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Recruiting\Enums\JobPostingStatus;
use Database\Factories\JobPostingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An advertised opening on the public job board (Phase 08b-i). Distinct from the
 * Property-Bible `positions` job-title catalog. `slug` is the public route key.
 *
 * @property JobPostingStatus $status
 */
class JobPosting extends Model
{
    /** @use HasFactory<JobPostingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'status',
        'title',
        'slug',
        'pay_range',
        'content',
        'hour_start',
        'hour_end',
        'property_id',
        'location_label',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobPostingStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * @param  Builder<JobPosting>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', JobPostingStatus::Published->value);
    }

    /** Human-readable location: the linked property's name, else the free-text label. */
    public function locationName(): ?string
    {
        if ($this->property_id !== null) {
            return $this->property->name;
        }

        return $this->location_label;
    }
}
