<?php

namespace App\Domain\People\Actions;

use App\Domain\People\Models\Person;
use App\Domain\People\Support\OnboardingChecklist;
use App\Domain\Shared\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stores one onboarding checklist document (Phase 08b-ii): persists the upload
 * to the configured disk, records it as a polymorphic File attached to the
 * person, and stamps the item's file/timestamp columns. Re-uploading replaces
 * the pointer (prior File rows stay attached as history); re-uploading the I-9
 * clears any earlier verification — the new document must be re-verified.
 */
class UploadOnboardingDocument
{
    public function handle(Person $person, string $item, UploadedFile $document, Person $actor): File
    {
        $definition = OnboardingChecklist::DOCUMENTS[$item] ?? null;

        if ($definition === null) {
            throw ValidationException::withMessages(['item' => "Unknown checklist item [{$item}]."]);
        }

        [, $fileColumn, $timestampColumn] = $definition;

        return DB::transaction(function () use ($person, $item, $document, $actor, $fileColumn, $timestampColumn) {
            $disk = (string) config('filesystems.default');
            $path = $document->store('onboarding', $disk);

            $file = File::create([
                'fileable_type' => $person->getMorphClass(),
                'fileable_id' => $person->getKey(),
                'disk' => $disk,
                'path' => $path,
                'original_name' => $document->getClientOriginalName(),
                'mime_type' => $document->getClientMimeType(),
                'size' => $document->getSize(),
                'uploaded_by' => $actor->id,
            ]);

            $attributes = [$fileColumn => $file->id, $timestampColumn => now()];

            if ($item === 'i9') {
                $attributes['i9_verified_by'] = null;
                $attributes['i9_verified_at'] = null;
            }

            $person->forceFill($attributes)->save();

            return $file;
        });
    }
}
