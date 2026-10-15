<?php

namespace App\Domain\PropertyBible\Support;

use App\Domain\PropertyBible\Actions\UploadWeekSchedule;
use App\Domain\PropertyBible\Models\WeekSchedule;
use App\Domain\Shared\Models\File;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Downloads for a week's schedule: one file as it was uploaded, or every file
 * of the week in one zip ("Acme Hotel schedule Oct 19 – 25.zip").
 */
class ScheduleDownload
{
    public function file(File $file): StreamedResponse
    {
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function week(WeekSchedule $schedule): StreamedResponse|BinaryFileResponse
    {
        $files = $schedule->files;
        abort_if($files->isEmpty(), 404);
        if ($files->count() === 1) {
            return $this->file($files->first());
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'schedule').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $used = [];
        foreach ($files as $file) {
            if (! Storage::disk($file->disk)->exists($file->path)) {
                continue;
            }
            // Two uploads with the same name both go in.
            $name = (string) $file->original_name;
            $base = $name;
            for ($i = 2; isset($used[$name]); $i++) {
                $name = pathinfo($base, PATHINFO_FILENAME)." ({$i}).".pathinfo($base, PATHINFO_EXTENSION);
            }
            $used[$name] = true;
            $zip->addFromString($name, (string) Storage::disk($file->disk)->get($file->path));
        }
        $zip->close();

        $range = UploadWeekSchedule::range(CarbonImmutable::parse($schedule->week_start->toDateString()));
        $name = Str::of("{$schedule->property->name} schedule {$range}")->replaceMatches('/[\\\\\/:*?"<>|]+/', ' ')->trim().'.zip';

        return response()->download($zipPath, $name)->deleteFileAfterSend();
    }
}
