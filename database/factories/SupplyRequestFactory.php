<?php

namespace Database\Factories;

use App\Domain\Inventory\Enums\BeneficiaryType;
use App\Domain\Inventory\Enums\SupplyRequestStatus;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\People\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplyRequest>
 */
class SupplyRequestFactory extends Factory
{
    protected $model = SupplyRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'beneficiary_type' => BeneficiaryType::SelfRequest,
            'quantity' => 1,
            'requested_by' => Person::factory(),
            'status' => SupplyRequestStatus::Pending,
        ];
    }
}
