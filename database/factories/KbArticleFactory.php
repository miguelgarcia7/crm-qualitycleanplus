<?php

namespace Database\Factories;

use App\Domain\KnowledgeBase\Enums\KbArticleStatus;
use App\Domain\KnowledgeBase\Models\KbArticle;
use App\Domain\People\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KbArticle>
 */
class KbArticleFactory extends Factory
{
    protected $model = KbArticle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(4), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'summary' => fake()->sentence(12),
            'content' => '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>',
            'author_id' => Person::factory(),
            'status' => KbArticleStatus::Draft,
            'version' => 1,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => KbArticleStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => KbArticleStatus::Archived]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }
}
