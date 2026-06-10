<?php

namespace Database\Factories;

use App\Domain\KnowledgeBase\Models\KbArticle;
use App\Domain\People\Models\Person;
use App\Domain\Shared\Enums\FeedbackType;
use App\Domain\Shared\Models\Feedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feedbackable_type' => (new KbArticle)->getMorphClass(),
            'feedbackable_id' => KbArticle::factory(),
            'person_id' => Person::factory(),
            'type' => FeedbackType::Helpful,
            'url' => '/admin/kb',
        ];
    }

    public function suggestion(): static
    {
        return $this->state(fn () => [
            'type' => FeedbackType::Suggestion,
            'message' => fake()->sentence(10),
        ]);
    }
}
