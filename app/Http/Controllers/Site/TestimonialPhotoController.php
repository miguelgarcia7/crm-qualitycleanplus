<?php

namespace App\Http\Controllers\Site;

use App\Domain\Marketing\Models\Testimonial;
use App\Domain\Shared\Models\File;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a testimonial's photo to the public home page. Photos sit on the
 * private default disk, so the site can't link the bucket directly; only
 * testimonials that are on the site are served. URLs carry `?v={file id}`, so
 * a replaced photo gets a new URL and the long public cache is safe.
 */
class TestimonialPhotoController extends Controller
{
    public function __invoke(Testimonial $testimonial): StreamedResponse
    {
        /** @var File|null $file */
        $file = $testimonial->is_active ? $testimonial->photoFile : null;
        abort_if($file === null, 404);

        return Storage::disk($file->disk)->response($file->path, null, ['Cache-Control' => 'public, max-age=604800']);
    }
}
