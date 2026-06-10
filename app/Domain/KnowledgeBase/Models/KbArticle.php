<?php

namespace App\Domain\KnowledgeBase\Models;

use App\Domain\KnowledgeBase\Enums\KbArticleStatus;
use App\Domain\People\Models\Person;
use App\Domain\Shared\Models\Feedback;
use App\Domain\Shared\Models\File;
use Carbon\CarbonImmutable;
use Database\Factories\KbArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role;

/**
 * A knowledge-base article (Phase 08c). `slug` is the route key (stable across
 * title edits). Every update snapshots the prior state into
 * `kb_article_versions` and bumps `version` (see UpdateKbArticle). Visibility:
 * zero roles attached = all authenticated users; otherwise role-gated;
 * super_admin bypasses. Readers only ever see `published`.
 *
 * @property KbArticleStatus $status
 * @property CarbonImmutable|null $published_at
 * @property bool $is_featured
 * @property int $view_count
 * @property int $version
 */
class KbArticle extends Model
{
    /** @use HasFactory<KbArticleFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Mirrors the column defaults so a freshly created (un-refreshed) model
     * can snapshot/bump `version` safely.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'view_count' => 0,
        'is_featured' => false,
        'version' => 1,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'summary',
        'content',
        'author_id',
        'last_edited_by',
        'status',
        'published_at',
        'view_count',
        'is_featured',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KbArticleStatus::class,
            'published_at' => 'datetime',
            'view_count' => 'integer',
            'is_featured' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'author_id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'last_edited_by');
    }

    /**
     * @return BelongsToMany<KbCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(KbCategory::class, 'kb_article_category', 'article_id', 'category_id');
    }

    /**
     * @return BelongsToMany<KbTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(KbTag::class, 'kb_article_tag', 'article_id', 'tag_id');
    }

    /**
     * Roles that may read this article; empty = visible to all authenticated users.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'kb_article_role', 'kb_article_id', 'role_id');
    }

    /**
     * @return HasMany<KbArticleVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(KbArticleVersion::class, 'article_id')->orderByDesc('version');
    }

    /**
     * @return MorphMany<File, $this>
     */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    /**
     * @return MorphMany<Feedback, $this>
     */
    public function feedback(): MorphMany
    {
        return $this->morphMany(Feedback::class, 'feedbackable');
    }

    /**
     * @param  Builder<KbArticle>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', KbArticleStatus::Published->value);
    }

    /**
     * Articles the given person may read (role visibility only — status is a
     * separate concern). Zero attached roles = everyone; super_admin bypasses.
     *
     * @param  Builder<KbArticle>  $query
     */
    public function scopeVisibleTo(Builder $query, Person $person): void
    {
        if ($person->hasRole('super_admin')) {
            return;
        }

        $roleIds = $person->roles->pluck('id');

        $query->where(function (Builder $q) use ($roleIds) {
            $q->whereDoesntHave('roles')
                ->orWhereHas('roles', fn (Builder $r) => $r->whereIn('roles.id', $roleIds));
        });
    }

    public function isVisibleTo(Person $person): bool
    {
        if ($person->hasRole('super_admin')) {
            return true;
        }

        $required = $this->roles;

        return $required->isEmpty()
            || $required->pluck('id')->intersect($person->roles->pluck('id'))->isNotEmpty();
    }

    /**
     * Snapshot the *current* (pre-edit) state into the immutable version
     * history. Called before applying an update; the snapshot's author is
     * whoever wrote the state being preserved.
     */
    public function snapshotVersion(?string $changeSummary = null): KbArticleVersion
    {
        return $this->versions()->create([
            'version' => $this->version,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->content,
            'author_id' => $this->last_edited_by ?? $this->author_id,
            'change_summary' => $changeSummary,
            'created_at' => now(),
        ]);
    }

    /** Publish; `published_at` is set on first publish only. */
    public function publish(): void
    {
        $this->forceFill([
            'status' => KbArticleStatus::Published,
            'published_at' => $this->published_at ?? now(),
        ])->save();
    }

    public function unpublish(): void
    {
        $this->forceFill(['status' => KbArticleStatus::Draft])->save();
    }

    public function archive(): void
    {
        $this->forceFill(['status' => KbArticleStatus::Archived])->save();
    }
}
