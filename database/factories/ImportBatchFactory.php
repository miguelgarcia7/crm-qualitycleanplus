<?php

namespace Database\Factories;

use App\Domain\Imports\Enums\ImportBatchStatus;
use App\Domain\Imports\Models\ImportBatch;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    protected $model = ImportBatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'payroll_period_id' => PayrollPeriod::factory(),
            'file_name' => 'hours-'.fake()->date('Y-m-d').'.xlsx',
            'file_hash' => hash('sha256', fake()->unique()->uuid()),
            'status' => ImportBatchStatus::Preview,
            'uploaded_by' => Person::factory(),
        ];
    }

    public function applied(): static
    {
        return $this->state(fn () => [
            'status' => ImportBatchStatus::Applied,
            'applied_at' => now(),
        ]);
    }
}
