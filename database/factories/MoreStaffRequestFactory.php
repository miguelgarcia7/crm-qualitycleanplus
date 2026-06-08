<?php

namespace Database\Factories;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Enums\MoreStaffUrgency;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MoreStaffRequest>
 */
class MoreStaffRequestFactory extends Factory
{
    protected $model = MoreStaffRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'position_id' => Position::factory(),
            'quantity_requested' => 2,
            'quantity_fulfilled' => 0,
            'by_date' => now()->addWeeks(2)->toDateString(),
            'urgency' => MoreStaffUrgency::Normal,
            'reason' => 'Seasonal volume increase',
            'status' => MoreStaffStatus::Submitted,
            'initiated_by' => Person::factory(),
            'assigned_recruiter_id' => Person::factory(),
        ];
    }
}
