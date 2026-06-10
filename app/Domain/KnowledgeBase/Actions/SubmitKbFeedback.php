<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\KnowledgeBase\Models\KbArticle;
use App\Domain\People\Models\Person;
use App\Domain\Shared\Enums\FeedbackType;
use App\Domain\Shared\Models\Feedback;
use Illuminate\Validation\ValidationException;

/**
 * Records reader feedback on a KB article (Phase 08c). Votes (helpful /
 * not_helpful) are one-per-person-per-article: voting again with the same
 * type is rejected; a different type flips the existing vote. Suggestions,
 * issues, and questions are unlimited and land in the admin queue.
 */
class SubmitKbFeedback
{
    public function handle(
        KbArticle $article,
        Person $person,
        FeedbackType $type,
        ?string $message,
        ?string $url = null,
        ?string $userAgent = null,
    ): Feedback {
        if ($type->isVote()) {
            /** @var Feedback|null $existing */
            $existing = $article->feedback()->votes()->where('person_id', $person->id)->first();

            if ($existing !== null) {
                if ($existing->type === $type) {
                    throw ValidationException::withMessages([
                        'feedback' => 'Your vote on this article is already recorded.',
                    ]);
                }

                $existing->update(['type' => $type]);

                return $existing;
            }
        }

        return $article->feedback()->create([
            'person_id' => $person->id,
            'type' => $type,
            'message' => $message,
            'url' => $url,
            'user_agent' => $userAgent,
        ]);
    }
}
