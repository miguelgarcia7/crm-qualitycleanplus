<?php

namespace App\Http\Controllers;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only viewer over the Spatie activity log (Phase 09c). Unlike other
 * list pages, this one paginates and filters SERVER-side — the log grows
 * without bound, so shipping the whole table to the client is not an option.
 * Route-gated `audit.activity_log.view` (admin, office_manager).
 */
class AuditLogController extends Controller
{
    /** Subject types that have a profile/detail page to link to. */
    private const SUBJECT_LINKS = [
        Person::class => '/admin/people/%d',
        Property::class => '/admin/properties/%d',
    ];

    public function index(Request $request): Response
    {
        $query = Activity::query()->with('causer')->latest()->latest('id');

        if ($search = $request->string('q')->trim()->value()) {
            $query->where('description', 'like', "%{$search}%");
        }

        if ($log = $request->string('log')->value()) {
            $query->where('log_name', $log);
        }

        if ($event = $request->string('event')->value()) {
            $query->where('event', $event);
        }

        if ($subject = $request->string('subject')->value()) {
            $query->where('subject_type', $subject);
        }

        $page = $query->paginate(25)->withQueryString();

        return Inertia::render('admin/audit/index', [
            'entries' => collect($page->items())->map(fn (Activity $a): array => [
                'id' => $a->id,
                'description' => $a->description,
                'event' => $a->event,
                'log_name' => $a->log_name,
                'subject_type' => $a->subject_type ? class_basename($a->subject_type) : null,
                'subject_url' => $this->subjectUrl($a),
                'causer' => $a->causer instanceof Person ? $a->causer->name : null,
                'causer_url' => $a->causer instanceof Person ? "/admin/people/{$a->causer->id}" : null,
                'created_at' => $a->created_at?->toDayDateTimeString(),
            ])->all(),
            'pagination' => [
                'page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'filters' => [
                'q' => $request->string('q')->value(),
                'log' => $request->string('log')->value(),
                'event' => $request->string('event')->value(),
                'subject' => $request->string('subject')->value(),
            ],
            'options' => [
                'logs' => Activity::query()->whereNotNull('log_name')->distinct()->orderBy('log_name')->pluck('log_name'),
                'events' => Activity::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event'),
                'subjects' => Activity::query()
                    ->whereNotNull('subject_type')
                    ->distinct()
                    ->orderBy('subject_type')
                    ->pluck('subject_type')
                    ->map(fn (string $type): array => ['value' => $type, 'label' => class_basename($type)]),
            ],
        ]);
    }

    private function subjectUrl(Activity $activity): ?string
    {
        $template = self::SUBJECT_LINKS[$activity->subject_type] ?? null;

        return $template !== null && $activity->subject_id !== null
            ? sprintf($template, $activity->subject_id)
            : null;
    }
}
