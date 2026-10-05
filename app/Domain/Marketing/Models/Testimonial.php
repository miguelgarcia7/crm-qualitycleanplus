<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Marketing\Enums\TestimonialSource;
use App\Domain\Shared\Models\File;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A review shown in the marketing home page's testimonials carousel, managed at
 * /admin/testimonials. Hidden ones stay for the record but never reach the site.
 *
 * The photo is a {@see File} on the default (private) disk, so the site serves
 * it through `marketing.testimonials.photo` rather than a bucket URL.
 *
 * @property TestimonialSource $source
 * @property bool $is_active
 */
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'quote',
        'source',
        'rating',
        'is_active',
        'photo_file_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => TestimonialSource::class,
            'rating' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function photoFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'photo_file_id');
    }

    /**
     * @param  Builder<Testimonial>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Public URL of the photo (cache-busted by file id), or null when there is none. */
    public function photoUrl(): ?string
    {
        if ($this->photo_file_id === null) {
            return null;
        }

        return route('marketing.testimonials.photo', ['testimonial' => $this->id, 'v' => $this->photo_file_id], false);
    }
}
