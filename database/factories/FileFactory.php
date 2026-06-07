<?php

namespace Database\Factories;

use App\Domain\Shared\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->slug().'.pdf';

        return [
            'disk' => 'local',
            'path' => 'contracts/'.fake()->uuid().'.pdf',
            'original_name' => $name,
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(10_000, 5_000_000),
        ];
    }
}
