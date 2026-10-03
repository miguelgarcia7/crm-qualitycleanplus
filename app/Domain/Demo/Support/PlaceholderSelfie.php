<?php

namespace App\Domain\Demo\Support;

use Illuminate\Http\UploadedFile;

/**
 * A stand-in clock selfie for simulated punches (ClockInContractor requires
 * one): a head-and-shoulders silhouette on a per-person background colour, so
 * the punch photos in the grid still tell contractors apart.
 */
final class PlaceholderSelfie
{
    private const BACKGROUNDS = [[86, 116, 160], [160, 104, 86], [96, 140, 104], [140, 108, 156], [176, 140, 72], [84, 140, 148]];

    /** A temp JPEG for this person; the caller deletes it once stored. */
    public static function for(int $personId): UploadedFile
    {
        $image = imagecreatetruecolor(240, 320);
        [$r, $g, $b] = self::BACKGROUNDS[$personId % count(self::BACKGROUNDS)];
        imagefill($image, 0, 0, (int) imagecolorallocate($image, $r, $g, $b));

        $figure = (int) imagecolorallocate($image, 232, 232, 236);
        imagefilledellipse($image, 120, 128, 104, 120, $figure);  // head
        imagefilledellipse($image, 120, 330, 220, 190, $figure);  // shoulders

        $path = (string) tempnam(sys_get_temp_dir(), 'demo-selfie');
        imagejpeg($image, $path, 80);

        return new UploadedFile($path, 'selfie.jpg', 'image/jpeg', null, true);
    }
}
