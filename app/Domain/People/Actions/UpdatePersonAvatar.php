<?php

namespace App\Domain\People\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Shared\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sets or clears a person's profile photo. Photos live on the private disk as
 * polymorphic {@see File} rows (same pattern as selfies/onboarding docs) and are
 * streamed via the authenticated avatar route. Unlike onboarding documents,
 * replaced avatars carry no retention requirement, so the old bytes are removed.
 */
class UpdatePersonAvatar
{
    public function handle(Person $person, UploadedFile $photo): File
    {
        return DB::transaction(function () use ($person, $photo): File {
            $disk = (string) config('filesystems.default');
            $path = $photo->store('avatars', $disk);

            $file = File::create([
                'fileable_type' => $person->getMorphClass(),
                'fileable_id' => $person->getKey(),
                'disk' => $disk,
                'path' => $path,
                'original_name' => $photo->getClientOriginalName(),
                'mime_type' => $photo->getClientMimeType(),
                'size' => $photo->getSize(),
                'uploaded_by' => $person->id,
            ]);

            $old = $person->avatarFile;
            $person->forceFill(['avatar_file_id' => $file->id])->save();
            $this->forget($old);

            return $file;
        });
    }

    public function remove(Person $person): void
    {
        DB::transaction(function () use ($person): void {
            $old = $person->avatarFile;
            $person->forceFill(['avatar_file_id' => null])->save();
            $this->forget($old);
        });
    }

    private function forget(?File $file): void
    {
        if ($file === null) {
            return;
        }

        Storage::disk($file->disk)->delete($file->path);
        $file->delete();
    }
}
