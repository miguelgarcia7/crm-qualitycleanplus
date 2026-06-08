<?php

namespace Database\Factories;

use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => PurchaseOrderStatus::Draft,
            'notes' => null,
            'source_request_id' => null,
        ];
    }
}
