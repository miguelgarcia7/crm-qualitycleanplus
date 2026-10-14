<?php

use App\Domain\Shared\Models\File;
use App\Domain\Time\Models\TimeEntry;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-10-13 15:00:00');
});

/** A QR punch with GPS and both selfies, as ClockInContractor/ClockOutContractor leave it. */
function qrPunch(string $startUtc): TimeEntry
{
    $entry = TimeEntry::factory()->create([
        'clock_method' => 'qr', 'start_at_utc' => $startUtc, 'end_at_utc' => date('Y-m-d H:i:s', strtotime($startUtc) + 8 * 3600),
        'clock_in_gps_lat' => 41.8781, 'clock_in_gps_lng' => -87.6298, 'clock_in_gps_accuracy_meters' => 10,
        'clock_out_gps_lat' => 41.8781, 'clock_out_gps_lng' => -87.6298, 'clock_out_gps_accuracy_meters' => 14,
        'clock_in_gps_flag_reason' => 'outside_geofence',
    ]);
    $selfie = function (string $side) use ($entry): File {
        $file = File::create([
            'fileable_type' => $entry->getMorphClass(), 'fileable_id' => $entry->id, 'disk' => 'local', 'path' => "selfies/{$entry->id}-{$side}.jpg",
            'original_name' => 'selfie.jpg', 'mime_type' => 'image/jpeg', 'size' => 10,
        ]);
        Storage::disk('local')->put($file->path, 'jpg');

        return $file;
    };
    $entry->update(['clock_in_selfie_file_id' => $selfie('in')->id, 'clock_out_selfie_file_id' => $selfie('out')->id]);

    return $entry->fresh();
}

it('deletes clock-in selfies and GPS a year on, and keeps the punch as it was', function () {
    $old = qrPunch('2025-09-01 14:00:00');
    $files = [$old->clock_in_selfie_file_id, $old->clock_out_selfie_file_id];
    $paths = File::query()->whereKey($files)->pluck('path')->all();
    $updatedAt = $old->updated_at;
    $recent = qrPunch('2026-01-10 14:00:00');

    $this->artisan('time-entries:purge')->expectsOutput('Purged selfies and GPS from 1 punch.')->assertSuccessful();

    $old->refresh();
    expect($old->clock_in_selfie_file_id)->toBeNull()
        ->and($old->clock_out_selfie_file_id)->toBeNull()
        ->and($old->clock_in_gps_lat)->toBeNull()
        ->and($old->clock_in_gps_accuracy_meters)->toBeNull()
        ->and($old->clock_out_gps_lng)->toBeNull()
        ->and($old->clock_in_gps_flag_reason)->toBe('outside_geofence')
        ->and($old->duration_minutes)->toBe(480)
        ->and($old->pay_rate_snapshot)->toBe(2000)
        ->and($old->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and(File::withTrashed()->whereKey($files)->count())->toBe(0);
    foreach ($paths as $path) {
        expect(Storage::disk('local')->exists($path))->toBeFalse();
    }

    $recent->refresh();
    expect($recent->clock_in_gps_lat)->not->toBeNull()
        ->and($recent->clock_in_selfie_file_id)->not->toBeNull();
});

it('skips punches of someone on legal hold, and purges deleted punches too', function () {
    $held = qrPunch('2025-08-01 14:00:00');
    $held->person->setLegalHold('Lawsuit');
    $deleted = qrPunch('2025-07-01 14:00:00');
    $deleted->delete();

    $this->artisan('time-entries:purge')->expectsOutput('Purged selfies and GPS from 1 punch.')->assertSuccessful();

    expect($held->fresh()->clock_in_selfie_file_id)->not->toBeNull()
        ->and(TimeEntry::withTrashed()->find($deleted->id)->clock_in_gps_lat)->toBeNull();
});

it('runs on the nightly schedule', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('time-entries:purge')->assertSuccessful();
});
