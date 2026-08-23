<?php

namespace App\Domain\PropertyBible\Models;

use App\Domain\PropertyBible\Enums\HolidayType;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A holiday in the global calendar. Legal holidays are seeded with a
 * recurrence rule from HolidayDateResolver's vocabulary and are read-only;
 * custom holidays are fixed month/day dates repeating every year. Properties
 * opt in via the property_holiday pivot — only attached holidays affect that
 * property's time bucketing.
 *
 * @property HolidayType $type
 * @property-read int|null $properties_count
 */
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    /** Seeded legal holidays: slug => [name, rule]. */
    public const LEGAL_HOLIDAYS = [
        'new_years_day' => ['New Year\'s Day', 'january_1'],
        'memorial_day' => ['Memorial Day', 'last_monday_may'],
        'independence_day' => ['Independence Day', 'july_4'],
        'labor_day' => ['Labor Day', 'first_monday_september'],
        'thanksgiving_day' => ['Thanksgiving Day', 'fourth_thursday_november'],
        'christmas_eve' => ['Christmas Eve', 'december_24'],
        'christmas_day' => ['Christmas Day', 'december_25'],
    ];

    /** Auto-attached to every new property. */
    public const DEFAULT_ENABLED_SLUGS = ['new_years_day', 'labor_day', 'thanksgiving_day', 'christmas_day'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'type',
        'rule',
        'month',
        'day',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HolidayType::class,
            'month' => 'integer',
            'day' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Property, $this>
     */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_holiday')->withTimestamps();
    }
}
