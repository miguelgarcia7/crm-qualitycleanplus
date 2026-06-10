<?php

namespace App\Domain\KnowledgeBase\Models;

use Database\Factories\KbTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A flat KB tag (Phase 08c). Auto-created when authors type new ones; `slug`
 * is the route key.
 */
class KbTag extends Model
{
    /** @use HasFactory<KbTagFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsToMany<KbArticle, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(KbArticle::class, 'kb_article_tag', 'tag_id', 'article_id');
    }

    /** Find by slugified name or create — authors type free-form tags. */
    public static function findOrCreateByName(string $name): self
    {
        $name = trim($name);

        return self::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name],
        );
    }
}
