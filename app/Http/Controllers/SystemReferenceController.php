<?php

namespace App\Http\Controllers;

use App\Domain\SystemReference\Support\AutomationCatalog;
use App\Domain\SystemReference\Support\PermissionCatalog;
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
}
