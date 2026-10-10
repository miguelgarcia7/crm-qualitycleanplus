<?php

namespace App\Http\Controllers;

use App\Domain\SystemReference\Support\AutomationCatalog;
use App\Domain\SystemReference\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → System reference: how QCP is set up (who can do what, who is
 * notified, what runs on its own), read from the live app rather than written
 * down, so it can't drift. This is the overview and its lookup; each topic
 * gets its own page as it's built. Route-gated `system.reference.view`
 * (admin; super_admin holds every permission).
 */
class SystemReferenceController extends Controller
{
    public function index(PermissionCatalog $permissions, AutomationCatalog $automations): Response
    {
        $permissionRows = $permissions->all();
        $automationRows = $automations->all();

        return Inertia::render('admin/system/index', [
            'stats' => [
                'permissions' => count($permissionRows),
                'roles' => $permissions->roleCount(),
                'automations' => count($automationRows),
            ],
            'permissions' => array_map(fn (array $p): array => [
                'key' => $p['key'],
                'label' => $p['label'],
                'area' => $p['area'],
                // Super Admin holds everything; naming it on every row is noise.
                'roles' => array_values(array_map(
                    fn (string $role): string => PermissionCatalog::ROLE_LABELS[$role],
                    array_filter($p['roles'], fn (string $role): bool => $role !== 'super_admin'),
                )),
            ], $permissionRows),
            'automations' => $automationRows,
            'timezone' => 'Chicago time',
        ]);
    }

    /**
     * Every permission against every role, grouped by area. Super Admin holds
     * everything, so it isn't a column; a permission no other role holds is
     * marked "Super Admin only". `?role=` highlights a column and `?q=`
     * pre-fills the search, so other pages can link straight to an answer.
     */
    public function roles(Request $request, PermissionCatalog $permissions): Response
    {
        $roles = array_diff_key(PermissionCatalog::ROLE_LABELS, ['super_admin' => true]);
        $groups = $permissions->grouped();

        $totals = array_fill_keys(array_keys($roles), 0);
        foreach ($groups as $group) {
            foreach ($group['permissions'] as $p) {
                foreach ($p['roles'] as $role) {
                    if (isset($totals[$role])) {
                        $totals[$role]++;
                    }
                }
            }
        }

        $role = $request->string('role')->value();

        return Inertia::render('admin/system/roles', [
            'roles' => array_map(
                fn (string $key, string $label): array => ['key' => $key, 'label' => $label, 'total' => $totals[$key]],
                array_keys($roles),
                $roles,
            ),
            'groups' => array_map(fn (array $group): array => [
                'name' => $group['name'],
                'permissions' => array_map(function (array $p) use ($permissions): array {
                    $holders = array_values(array_filter($p['roles'], fn (string $r): bool => $r !== 'super_admin'));

                    return [
                        'key' => $p['key'],
                        'label' => $p['label'],
                        'roles' => $holders,
                        'super_only' => $holders === [],
                        'scope' => $permissions->scope($p['key']),
                    ];
                }, $group['permissions']),
            ], $groups),
            'total' => array_sum(array_map(fn (array $g): int => count($g['permissions']), $groups)),
            'initial' => [
                'role' => array_key_exists($role, $roles) ? $role : null,
                'q' => $request->string('q')->trim()->limit(100, '')->value(),
            ],
        ]);
    }
}
