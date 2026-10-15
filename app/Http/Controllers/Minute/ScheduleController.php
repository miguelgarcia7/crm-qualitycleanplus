<?php

namespace App\Http\Controllers\Minute;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Actions\RemoveScheduleFile;
use App\Domain\PropertyBible\Actions\UploadWeekSchedule;
use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\WeekSchedule;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\PropertyBible\Support\ScheduleCalendar;
use App\Domain\PropertyBible\Support\ScheduleDownload;
use App\Domain\Shared\Models\File;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * QC Minute → Schedules: the property manager uploads their hotel's own
 * schedule for each work week, in whatever files they keep it, on a month
 * calendar (one row per week; cards on a phone). Their recruiter is told and
 * can download it. Routes gated `schedules.upload`, scoped to the PM's hotels.
 */
class ScheduleController extends Controller
{
    /** File kinds a hotel schedule comes in. */
    private const MIMES = 'pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp,heic,heif';

    public function index(Request $request, ScheduleCalendar $calendar): Response
    {
        $properties = $this->properties($this->person($request));
        $property = $properties->firstWhere('id', $request->integer('property')) ?? $properties->first();

        $month = $property === null ? null : CarbonImmutable::parse($property->localDate())->startOfMonth();
        if ($property !== null && preg_match('/^\d{4}-\d{2}$/', $request->string('month')->value()) === 1) {
            $month = CarbonImmutable::parse($request->string('month')->value().'-01');
        }

        return Inertia::render('minute/schedules/index', [
            'properties' => $properties->map(fn (Property $p): array => ['id' => $p->id, 'name' => $p->name])->values(),
            'property' => $property === null ? null : ['id' => $property->id, 'name' => $property->name],
            'calendar' => $property === null || $month === null ? null : $calendar->month($property, $month),
            'month' => $month?->format('Y-m'),
        ]);
    }

    public function store(Request $request, Property $property, string $week, UploadWeekSchedule $action): RedirectResponse
    {
        $person = $this->person($request);
        $this->authorizeProperty($person, $property);

        $validated = $request->validate([
            'files' => ['array', 'max:10'],
            'files.*' => ['file', 'mimes:'.self::MIMES, 'max:20480'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'files.*.mimes' => 'Upload a PDF, Word or Excel file, or a photo.',
            'files.*.max' => 'Each file can be up to 20 MB.',
            'files.max' => 'Up to 10 files at a time.',
        ]);

        $files = array_values($request->file('files', []));
        if ($files === [] && blank($validated['note'] ?? null)) {
            throw ValidationException::withMessages(['files' => 'Choose a file to upload.']);
        }

        $action->handle($property, CarbonImmutable::parse($week), $files, $validated['note'] ?? null, $person);

        return back()->with('success', $files === [] ? 'Note saved.' : 'Schedule uploaded.');
    }

    public function destroyFile(Request $request, File $file, RemoveScheduleFile $action): RedirectResponse
    {
        $person = $this->person($request);
        $schedule = $this->scheduleOf($file);
        $this->authorizeProperty($person, $schedule->property);

        $action->handle($schedule, $file, $person);

        return back()->with('success', 'File removed.');
    }

    public function download(Request $request, File $file, ScheduleDownload $download): StreamedResponse
    {
        $schedule = $this->scheduleOf($file);
        $this->authorizeProperty($this->person($request), $schedule->property);

        return $download->file($file);
    }

    private function scheduleOf(File $file): WeekSchedule
    {
        $schedule = $file->fileable;
        abort_unless($schedule instanceof WeekSchedule, 404);

        return $schedule;
    }

    /**
     * The hotels this person uploads for: their own, or every active one for
     * the global roles.
     *
     * @return Collection<int, Property>
     */
    private function properties(Person $person): Collection
    {
        return Property::query()
            ->when(! $person->hasAnyRole(PropertyPolicy::GLOBAL_ROLES), fn ($q) => $q->assignedTo($person))
            ->where('status', PropertyStatus::Active->value)
            ->orderBy('name')
            ->get();
    }

    private function authorizeProperty(Person $person, Property $property): void
    {
        abort_unless($person->can('schedules.upload'), 403);
        abort_unless($person->hasAnyRole(PropertyPolicy::GLOBAL_ROLES) || $person->isAssignedTo($property), 403);
    }

    private function person(Request $request): Person
    {
        $user = $request->user();
        abort_unless($user instanceof Person, 403);

        return $user;
    }
}
