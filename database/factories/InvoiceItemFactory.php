<?php

namespace Database\Factories;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoiceItem;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'work_order_id' => WorkOrder::factory(),
            'contractor_name' => fake()->name(),
            'position_name' => 'Housekeeper',
            'pay_rate' => 2000,
            'ot_pay_rate' => 3000,
            'bill_rate' => 3200,
            'ot_bill_rate' => 4800,
            'regular_minutes' => 2400,
            'overtime_minutes' => 0,
            'total_bill' => 128000,
            'total_payout' => 80000,
        ];
    }
}
