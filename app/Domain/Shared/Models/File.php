<?php

namespace App\Domain\Shared\Models;

use App\Domain\People\Models\Person;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A polymorphic uploaded file (contract documents now; KB/invoices later).
 */
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fileable_type',
        'fileable_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'uploaded_by');
    }
}
