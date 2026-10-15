<?php

use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\WeekSchedule;
use App\Domain\Shared\Models\File;
use App\Notifications\ScheduleUploadedNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake((string) config('filesystems.default'));
    $this->travelTo('2026-10-14 15:00:00'); // Wed, Oct 14 in Chicago
    $this->hotel = Property::factory()->create(['name' => 'Acme Hotel', 'timezone' => 'America/Chicago', 'closing_day' => 7]);
    $this->pm = tap(person('property_manager'))->update(['name' => 'Pat Manager']);
    $this->rita = tap(person('recruiter'))->update(['name' => 'Rita Recruiter']);
    $this->hotel->assignments()->create(['person_id' => $this->pm->id, 'role' => PropertyAssignmentRole::PropertyManager->value]);
    $this->hotel->assignments()->create(['person_id' => $this->rita->id, 'role' => PropertyAssignmentRole::Recruiter->value]);
});

/** Pat uploads to a week through the real endpoint. */
function uploadSchedule($test, string $week, array $files, ?string $note = null)
{
    return $test->actingAs($test->pm)->post(qcminute("/schedules/{$test->hotel->id}/{$week}"), ['files' => $files, 'note' => $note]);
}

it('shows the property manager a month of work weeks, past ones closed', function () {
    $this->actingAs($this->pm)->get(qcminute('/schedules'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('minute/schedules/index')
            ->where('property.name', 'Acme Hotel')
            ->where('calendar.label', 'October 2026')
            ->where('calendar.days', ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
            ->has('calendar.weeks', 5) // Sep 28 … Oct 26
            ->where('calendar.weeks.2.week_start', '2026-10-12')
            ->where('calendar.weeks.2.when', 'This week')
            ->where('calendar.weeks.2.can_change', true)
            ->where('calendar.weeks.3.when', 'Next week')
            ->where('calendar.weeks.3.long_label', 'Mon, Oct 19 – Sun, Oct 25')
            ->where('calendar.weeks.1.can_change', false)
            ->where('calendar.weeks.2.days.2.today', true));
});

it('uploads several files for a week, tells the recruiter, and logs it', function () {
    Notification::fake();

    uploadSchedule($this, '2026-10-19', [
        UploadedFile::fake()->create('Housekeeping wk 43.pdf', 300, 'application/pdf'),
        UploadedFile::fake()->image('Banquets whiteboard.jpg'),
    ], 'Big wedding Saturday')->assertSessionHasNoErrors();

    $schedule = WeekSchedule::query()->firstOrFail();
    expect($schedule->week_start->toDateString())->toBe('2026-10-19')
        ->and($schedule->note)->toBe('Big wedding Saturday')
        ->and($schedule->files()->pluck('original_name')->all())->toBe(['Housekeeping wk 43.pdf', 'Banquets whiteboard.jpg']);
    foreach ($schedule->files as $file) {
        Storage::disk($file->disk)->assertExists($file->path);
    }

    Notification::assertSentTo($this->rita, ScheduleUploadedNotification::class,
        fn (ScheduleUploadedNotification $n) => $n->toArray($this->rita)['message'] === 'Pat Manager uploaded the schedule for Acme Hotel, Oct 19 – 25: 2 files. Note: Big wedding Saturday');
    expect(Activity::query()->where('subject_id', $this->hotel->id)->latest('id')->value('description'))
        ->toBe('Uploaded 2 schedule files for Oct 19 – 25');

    // More files later go next to the first ones, on the same week.
    uploadSchedule($this, '2026-10-19', [UploadedFile::fake()->create('Banquets v2.xlsx', 20)], 'Big wedding Saturday')->assertSessionHasNoErrors();
    expect(WeekSchedule::query()->count())->toBe(1)->and($schedule->files()->count())->toBe(3);

    $this->actingAs($this->pm)->get(qcminute('/schedules'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('calendar.weeks.3.files', 3)
            ->where('calendar.weeks.3.files.0.type', 'PDF')
            ->where('calendar.weeks.3.files.2.type', 'XLS')
            ->where('calendar.weeks.3.note', 'Big wedding Saturday'));
});

it('keeps a finished week as it was, and other hotels out', function () {
    uploadSchedule($this, '2026-10-05', [UploadedFile::fake()->create('late.pdf', 10)])
        ->assertSessionHasErrors(['files' => 'That week is over. Its schedule can no longer change.']);

    $other = Property::factory()->create();
    $this->actingAs($this->pm)->post(qcminute("/schedules/{$other->id}/2026-10-19"), ['files' => [UploadedFile::fake()->create('x.pdf', 10)]])
        ->assertForbidden();

    $this->actingAs($this->rita)->get(qcminute('/schedules'))->assertForbidden();
    expect(WeekSchedule::query()->count())->toBe(0);
});

it('refuses files that aren\'t a schedule, and an empty upload', function () {
    uploadSchedule($this, '2026-10-19', [UploadedFile::fake()->create('virus.exe', 10)])
        ->assertSessionHasErrors(['files.0' => 'Upload a PDF, Word or Excel file, or a photo.']);
    uploadSchedule($this, '2026-10-19', [])
        ->assertSessionHasErrors(['files' => 'Choose a file to upload.']);
});

it('removes a file while the week is open, not after', function () {
    uploadSchedule($this, '2026-10-12', [UploadedFile::fake()->create('a.pdf', 10), UploadedFile::fake()->create('b.pdf', 10)]);
    [$a, $b] = File::query()->orderBy('id')->get()->all();

    $this->actingAs($this->pm)->delete(qcminute("/schedules/files/{$a->id}"))->assertSessionHasNoErrors();
    expect(File::withTrashed()->find($a->id))->toBeNull();
    Storage::disk($a->disk)->assertMissing($a->path);

    $this->travelTo('2026-10-19 15:00:00'); // the week is over
    $this->actingAs($this->pm)->delete(qcminute("/schedules/files/{$b->id}"))
        ->assertSessionHasErrors(['file' => 'That week is over. Its schedule can no longer change.']);
    expect(File::find($b->id))->not->toBeNull();
});

it('lets the hotel\'s recruiter download the week: one file as is, several as a zip', function () {
    uploadSchedule($this, '2026-10-19', [UploadedFile::fake()->create('Housekeeping.pdf', 10)]);
    $schedule = WeekSchedule::query()->firstOrFail();
    $url = main("/admin/schedules/{$schedule->id}/download");

    $this->actingAs($this->rita)->get($url)->assertOk()->assertDownload('Housekeeping.pdf');

    uploadSchedule($this, '2026-10-19', [UploadedFile::fake()->create('Banquets.xlsx', 10)]);
    $zip = $this->actingAs($this->rita)->get($url)->assertOk();
    expect($zip->headers->get('content-disposition'))->toContain('Acme Hotel schedule Oct 19')->toContain('.zip');

    // From the bell (an Inertia visit), the browser is sent to the file.
    $this->actingAs($this->rita)->get($url, ['X-Inertia' => 'true'])->assertStatus(409)->assertHeader('X-Inertia-Location', $url);

    $this->actingAs(person('recruiter'))->get($url)->assertForbidden(); // not this hotel's recruiter
});

it('lets the property manager download their own file', function () {
    uploadSchedule($this, '2026-10-19', [UploadedFile::fake()->create('Housekeeping.pdf', 10)]);
    $file = File::query()->firstOrFail();

    $this->actingAs($this->pm)->get(qcminute("/schedules/files/{$file->id}"))->assertOk()->assertDownload('Housekeeping.pdf');
});

it('shows this week and the next two on the property manager\'s dashboard', function () {
    uploadSchedule($this, '2026-10-12', [UploadedFile::fake()->create('a.pdf', 10)]);

    $this->actingAs($this->pm)->get(qcminute('/'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('schedules.0.name', 'Acme Hotel')
            ->where('schedules.0.weeks.0.when', 'This week')
            ->where('schedules.0.weeks.0.files', 1)
            ->where('schedules.0.weeks.1.label', 'Oct 19 – 25')
            ->where('schedules.0.weeks.1.files', 0));
});
