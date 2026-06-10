<?php

namespace App\Domain\KnowledgeBase\Models;

use App\Domain\People\Models\Person;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable snapshot of a KB article taken before an edit (Phase 08c).
 * Never updated or deleted (cascades with its article). `created_at` is
 * managed manually — there is no `updated_at` column.
 *
 * @property int $version
 * @property CarbonImmutable|null $created_at
 */
class KbArticleVersion extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'article_id',
        'version',
        'title',
        'summary',
        'content',
        'author_id',
        'change_summary',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<KbArticle, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class, 'article_id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'author_id');
    }
}
