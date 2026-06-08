<?php

namespace Database\Factories;

use App\Domain\Imports\Enums\ImportRowStatus;
use App\Domain\Imports\Models\ImportBatch;
use App\Domain\Imports\Models\ImportBatchRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatchRow>
 */
class ImportBatchRowFactory extends Factory
{
    protected $model = ImportBatchRow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_batch_id' => ImportBatch::factory(),
            'row_number' => fake()->unique()->numberBetween(1, 500),
            'raw_data' => [
                'name' => fake()->name(),
                'external_id' => (string) fake()->numberBetween(1000, 9999),
                'hours' => 40.0,
                'pay_rate' => 20.0,
                'bill_rate' => 30.0,
                'start_date' => now()->startOfWeek()->toDateString(),
                'end_date' => now()->startOfWeek()->addDays(6)->toDateString(),
                'position' => 'Housekeeper',
            ],
            'status' => ImportRowStatus::Unmatched,
        ];
    }
}
