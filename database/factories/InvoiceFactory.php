<?php

namespace Database\Factories;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'payroll_period_id' => PayrollPeriod::factory(),
            'timesheet_id' => Timesheet::factory(),
            'invoice_number' => 'INV-2026-'.fake()->unique()->numerify('######'),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'property_snapshot' => ['name' => fake()->company()],
            'invoicer_snapshot' => ['name' => 'Quality Cleaning Plus'],
            'tax_rate' => 0.0875,
            'status' => InvoiceStatus::Invoiced,
        ];
    }
}
