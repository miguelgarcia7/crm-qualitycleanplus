<?php

namespace App\Http\Middleware;

use App\Domain\People\Models\Person;
use App\Notifications\NotificationLink;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Only Person users carry roles/permissions; a tablet Device (device guard)
        // authenticates the kiosk JSON APIs and must not be treated as an Inertia user.
        $user = $request->user() instanceof Person ? $request->user() : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'avatar' => $user?->avatarUrl(),
                // Shared so the UI (e.g. sidebar) can gate by permission/role.
                'permissions' => $user ? $user->getAllPermissions()->pluck('name')->values()->all() : [],
                'roles' => $user ? $user->getRoleNames()->values()->all() : [],
            ],
            // Which surface this request is on — shared components (TopBar) use it
            // to render surface-appropriate links instead of hardcoded /admin paths.
            'surface' => $request->getHost() === config('domains.qcminute') ? 'qcminute' : 'backoffice',
            'notifications' => fn () => $this->notifications($request, $user),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The notification-bell payload: unread count + the most recent items for
     * the dropdown, plus the URL base for this surface (`/admin/notifications`
     * on the back office, `/notifications` on QC Minute) so the bell can post
     * mark-read actions to the right domain.
     *
     * @return array{unread: int, items: array<int, array<string, mixed>>, base: string}|null
     */
    protected function notifications(Request $request, ?Person $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $onMinute = $request->getHost() === config('domains.qcminute');

        $items = $user->notifications()->latest()->limit(8)->get()->map(fn ($n) => [
            'id' => $n->id,
            'category' => $n->data['category'] ?? null,
            'message' => $n->data['message'] ?? null,
            'url' => NotificationLink::resolve($n->data, $onMinute),
            'read' => $n->read_at !== null,
            'ago' => $n->created_at?->diffForHumans(short: true),
        ])->all();

        return [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $items,
            'base' => $onMinute ? '/notifications' : '/admin/notifications',
        ];
    }
}
