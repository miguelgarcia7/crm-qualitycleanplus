<?php

namespace App\Domain\Time\Support;

use App\Domain\Shared\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Persists a clock/visit selfie to the configured disk and records it as a
 * polymorphic {@see File} attached to the owning record — a time entry (Phase 07a)
 * or a field visit (Phase 07b). Selfies follow the 1-year PII retention rule (ADR-0010).
 */
class StoreSelfie
{
    public static function for(UploadedFile $selfie, Model $fileable, ?int $uploadedBy): File
    {
        $disk = (string) config('filesystems.default');
        $path = $selfie->store('selfies', $disk);

        return File::create([
            'fileable_type' => $fileable->getMorphClass(),
            'fileable_id' => $fileable->getKey(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => $selfie->getClientOriginalName(),
            'mime_type' => $selfie->getClientMimeType(),
            'size' => $selfie->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);
    }
}
