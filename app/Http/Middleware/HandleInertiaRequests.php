<?php

namespace App\Http\Middleware;

use App\Domain\People\Models\Person;
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
                // Shared so the UI (e.g. sidebar) can gate by permission/role.
                'permissions' => $user ? $user->getAllPermissions()->pluck('name')->values()->all() : [],
                'roles' => $user ? $user->getRoleNames()->values()->all() : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
