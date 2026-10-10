<?php

namespace App\Domain\SystemReference\Support;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;

/**
 * One person's own access, for the "My access" tab on My Profile: their
 * roles, the properties they look after, what they can do (by area) and what
 * they'll be notified about. Built from the same catalogs as the System
 * reference pages, so it shows what this environment actually grants.
 */
class AccessSummary
{
    public function __construct(
        private readonly PermissionCatalog $permissions,
        private readonly NotificationCatalog $notifications,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Person $person): array
    {
        $roles = $person->getRoleNames()->all();
        $granted = array_flip($person->getAllPermissions()->pluck('name')->all());

        $areas = [];
        $total = 0;
        foreach ($this->permissions->grouped() as $group) {
            $can = $cant = [];
            foreach ($group['permissions'] as $p) {
                if (isset($granted[$p['key']])) {
                    $can[] = $p['label'];
                } else {
                    $cant[] = $p['label'];
                }
            }
            $total += count($group['permissions']);

            // An area they have nothing in would only be a list of "can't".
            if ($can !== []) {
                $areas[] = ['name' => $group['name'], 'can' => $can, 'cant' => $cant];
            }
        }

        $muted = $person->muted_notifications ?? [];
        $hasEmail = filled($person->email);
        $notices = [];
        foreach ($this->notifications->all() as $n) {
            $modes = array_intersect_key($n['audience'], array_flip($roles));
            // The invitation only ever goes to someone who has no login yet.
            if ($modes === [] || $n['id'] === 'invitation') {
                continue;
            }

            $notices[] = [
                'name' => $n['name'],
                'summary' => $n['summary'],
                // "always" wins when two of their roles disagree.
                'only_if_theirs' => ! in_array('always', $modes, true),
                'in_app' => $n['in_app'],
                'email' => $n['email'] && $hasEmail,
                'muted' => $n['mute_key'] !== null && in_array($n['mute_key'], $muted, true),
            ];
        }

        return [
            'roles' => array_map(fn (string $r): string => PermissionCatalog::ROLE_LABELS[$r] ?? $r, $roles),
            'is_super_admin' => in_array('super_admin', $roles, true),
            'properties' => $this->properties($person),
            // These roles see every property, assigned or not (PropertyPolicy).
            'all_properties' => $person->hasAnyRole(PropertyPolicy::GLOBAL_ROLES),
            'can_count' => array_sum(array_map(fn (array $a): int => count($a['can']), $areas)),
            'total' => $total,
            'areas' => $areas,
            'notices' => $notices,
            'has_email' => $hasEmail,
            'reference_url' => $person->can('system.reference.view') && $roles !== []
                ? '/admin/system/roles?role='.collect($roles)->first(fn (string $r): bool => $r !== 'super_admin', 'admin')
                : null,
        ];
    }

    /**
     * The properties this person looks after: their assignments (recruiters,
     * property managers), or the properties of their active work orders
     * (contractors).
     *
     * @return list<string>
     */
    private function properties(Person $person): array
    {
        $assigned = $person->assignedProperties()->orderBy('name')->pluck('name');

        $working = $person->workOrders()
            ->where('status', WorkOrderStatus::Active->value)
            ->with('property:id,name')
            ->get()
            ->pluck('property.name');

        return $assigned->merge($working)->filter()->unique()->sort()->values()->all();
    }
}
