<?php

namespace App\Http\Controllers;

use App\Domain\People\Models\Person;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a person's profile photo from the private disk. Avatars are not
 * public assets — any authenticated back-office user may see a colleague's
 * photo, but the bytes never get a public URL.
 */
class PersonAvatarController extends Controller
{
    public function __invoke(Person $person): StreamedResponse
    {
        $file = $person->avatarFile;

        abort_if($file === null, 404);

        return Storage::disk($file->disk)->response($file->path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
