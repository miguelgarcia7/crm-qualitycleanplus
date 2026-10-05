<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Marketing\Models\Testimonial;
use App\Domain\People\Actions\UpdatePersonAvatar;
use App\Domain\Shared\Models\File;
use Illuminate\Http\File as LocalFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sets or clears a testimonial's photo — same pattern as a person's avatar
 * ({@see UpdatePersonAvatar}): a {@see File} on the
 * default (private) disk, old bytes removed on replace. Takes an upload from the
 * back office or a local file from the legacy import.
 */
class UpdateTestimonialPhoto
{
    public function handle(Testimonial $testimonial, UploadedFile|LocalFile $photo, ?int $uploadedBy = null): File
    {
        return DB::transaction(function () use ($testimonial, $photo, $uploadedBy): File {
            $disk = (string) config('filesystems.default');
            $path = (string) Storage::disk($disk)->putFile('testimonials', $photo);

            $file = File::create([
                'fileable_type' => $testimonial->getMorphClass(),
                'fileable_id' => $testimonial->getKey(),
                'disk' => $disk,
                'path' => $path,
                'original_name' => $photo instanceof UploadedFile ? $photo->getClientOriginalName() : $photo->getFilename(),
                'mime_type' => $photo instanceof UploadedFile ? $photo->getClientMimeType() : (string) $photo->getMimeType(),
                'size' => $photo->getSize(),
                'uploaded_by' => $uploadedBy,
            ]);

            $old = $testimonial->photoFile;
            $testimonial->forceFill(['photo_file_id' => $file->id])->save();
            $this->forget($old);

            return $file;
        });
    }

    public function remove(Testimonial $testimonial): void
    {
        DB::transaction(function () use ($testimonial): void {
            $old = $testimonial->photoFile;
            $testimonial->forceFill(['photo_file_id' => null])->save();
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
