<?php

namespace App\Http\Controllers;

use App\Domain\People\Models\Person;
use App\Notifications\NotificationLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The in-app notification center, mounted on both surfaces (back office and
 * QC Minute). Notifications belong to the authenticated user (the notifiable),
 * so every query is naturally scoped to $request->user() — no cross-user reads.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Person $person */
        $person = $request->user();
        $onMinute = $request->getHost() === config('domains.qcminute');

        $history = $person->notifications()->latest()->limit(100)->get()->map(fn ($n) => [
            'id' => $n->id,
            'category' => $n->data['category'] ?? null,
            'message' => $n->data['message'] ?? null,
            'url' => NotificationLink::resolve($n->data, $onMinute),
            'read' => $n->read_at !== null,
            'ago' => $n->created_at?->diffForHumans(),
            'date' => $n->created_at?->toDayDateTimeString(),
        ])->all();

        // NB: keyed `history`, not `notifications` — the middleware shares a
        // `notifications` prop (the bell payload); a same-named page prop
        // would clobber it and blank the bell on this page.
        return Inertia::render('admin/notifications/index', [
            'history' => $history,
        ]);
    }

    /** Open one: mark read, then deep-link to its referenced entity. */
    public function open(Request $request, string $id): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->user();
        $notification = $person->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = NotificationLink::resolve($notification->data, $request->getHost() === config('domains.qcminute'));

        return $url ? redirect()->to($url) : back();
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->user();
        $person->notifications()->findOrFail($id)->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->user();
        $person->unreadNotifications->markAsRead();

        return back();
    }
}
