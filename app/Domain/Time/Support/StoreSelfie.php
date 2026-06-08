<?php

namespace App\Domain\Time\Support;

use App\Domain\Shared\Models\File;
use App\Domain\Time\Models\TimeEntry;
use Illuminate\Http\UploadedFile;

/**
 * Persists a clock-event selfie to the configured disk and records it as a
 * polymorphic {@see File} attached to the time entry (Phase 07a, ADR-0017).
 * Selfies follow the 1-year PII retention rule (ADR-0010).
 */
class StoreSelfie
{
    public static function for(UploadedFile $selfie, TimeEntry $entry, ?int $uploadedBy): File
    {
        $disk = (string) config('filesystems.default');
        $path = $selfie->store('selfies', $disk);

        return File::create([
            'fileable_type' => $entry->getMorphClass(),
            'fileable_id' => $entry->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $selfie->getClientOriginalName(),
            'mime_type' => $selfie->getClientMimeType(),
            'size' => $selfie->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);
    }
}
