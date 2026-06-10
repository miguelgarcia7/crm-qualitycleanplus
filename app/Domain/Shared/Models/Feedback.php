<?php

namespace App\Domain\Shared\Models;

use App\Domain\People\Models\Person;
use App\Domain\Shared\Enums\FeedbackType;
use Carbon\CarbonImmutable;
use Database\Factories\FeedbackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic feedback on a record (Phase 08c — KB articles first). Votes
 * (helpful / not_helpful) are deduplicated per person per record; suggestions,
 * issues, and questions are unlimited and feed the admin resolution queue.
 *
 * @property FeedbackType $type
 * @property bool $is_resolved
 * @property CarbonImmutable|null $resolved_at
 */
class Feedback extends Model
{
    /** @use HasFactory<FeedbackFactory> */
    use HasFactory;

    protected $table = 'feedback';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'feedbackable_type',
        'feedbackable_id',
        'person_id',
        'type',
        'message',
        'url',
        'user_agent',
        'is_resolved',
        'resolved_by',
        'resolved_at',
        'admin_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FeedbackType::class,
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function feedbackable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'resolved_by');
    }

    /**
     * @param  Builder<Feedback>  $query
     */
    public function scopeUnresolved(Builder $query): void
    {
        $query->where('is_resolved', false);
    }

    /**
     * Helpful / not-helpful votes only.
     *
     * @param  Builder<Feedback>  $query
     */
    public function scopeVotes(Builder $query): void
    {
        $query->whereIn('type', [FeedbackType::Helpful->value, FeedbackType::NotHelpful->value]);
    }

    public function markResolved(Person $resolver, ?string $notes = null): void
    {
        $this->forceFill([
            'is_resolved' => true,
            'resolved_by' => $resolver->id,
            'resolved_at' => now(),
            'admin_notes' => $notes ?? $this->admin_notes,
        ])->save();
    }

    public function markUnresolved(): void
    {
        $this->forceFill([
            'is_resolved' => false,
            'resolved_by' => null,
            'resolved_at' => null,
        ])->save();
    }
}
