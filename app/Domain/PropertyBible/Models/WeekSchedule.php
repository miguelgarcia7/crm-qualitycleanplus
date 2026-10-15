<?php

namespace App\Domain\PropertyBible\Models;

use App\Domain\People\Models\Person;
use App\Domain\Shared\Models\File;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A hotel's schedule for one work week (Mon–Sun, or the property's own week),
 * uploaded by its property manager: whatever files they keep it in, plus an
 * optional note for the recruiter. Files can be added or removed until the
 * week is over; after that the week is a record of what the hotel sent.
 *
 * @property CarbonImmutable $week_start
 */
class WeekSchedule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'week_start',
        'note',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return MorphMany<File, $this>
     */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable')->orderBy('id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'updated_by');
    }

    /** The week's last day ("Y-m-d"). */
    public function weekEnd(): string
    {
        return CarbonImmutable::parse($this->week_start->toDateString())->addDays(6)->toDateString();
    }

    /** Files can change until the week is over, at the hotel's own date. */
    public function isOpen(): bool
    {
        return $this->weekEnd() >= $this->property->localDate();
    }
}
