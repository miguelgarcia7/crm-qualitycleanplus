<?php

namespace App\Domain\LegacyImport\Steps;

use App\Domain\LegacyImport\Support\LegacyIdMap;
use App\Domain\LegacyImport\Support\PeopleMergeMap;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Legacy `users` → `people` (phase-final-cutover.md).
 *
 * Every human row comes over — soft-deleted included, as inactive statuses, per
 * the archive-vs-history rule. Device accounts are skipped (tablets re-onboard),
 * test rows are dropped, and duplicate registrations fold into their survivor via
 * PeopleMergeMap. Writes bypass the Person model on purpose: this is a bulk
 * historical load with its own timestamps, and application_date is immutable
 * only to *later* edits.
 */
class ImportPeople
{
    /** Legacy role name => role in this app. */
    private const ROLE_MAP = [
        'admin' => 'admin',
        'payroll' => 'payroll',
        'property_manager' => 'property_manager',
        'employee_manager' => 'recruiter', // legacy name for the recruiter role
        'Office Manager' => 'office_manager',
        'HR' => 'hr',
        'contractor' => 'contractor',
    ];

    public function __construct(private readonly LegacyIdMap $map) {}

    /** @return array<string, int> */
    public function handle(): array
    {
        $legacy = DB::connection('legacy');

        $roles = $this->legacyRolesByUser();
        $activeContractorIds = $legacy->table('work_orders')
            ->whereNull('deleted_at')
            ->where('start_date', '<=', now())
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->pluck('contractor_id')
            ->flip()
            ->all();

        $users = $legacy->table('users')->orderBy('id')->get();
        $losers = PeopleMergeMap::losers();
        $byId = $users->keyBy('id');

        $stats = ['imported' => 0, 'updated' => 0, 'merged' => 0, 'dropped' => 0, 'devices_skipped' => 0];
        $roleRows = [];
        $roleIds = DB::table('roles')->pluck('id', 'name')->all();
        $morphClass = (new Person)->getMorphClass();

        DB::transaction(function () use ($users, $byId, $roles, $activeContractorIds, $losers, $roleIds, $morphClass, &$stats, &$roleRows): void {
            foreach ($users as $user) {
                if (in_array($user->id, PeopleMergeMap::DROP, true)) {
                    $stats['dropped']++;

                    continue;
                }

                if (isset($losers[$user->id])) {
                    continue; // handled with their survivor below
                }

                $userRoles = $roles[$user->id] ?? [];

                if ($userRoles === ['device']) {
                    $stats['devices_skipped']++;

                    continue;
                }

                $group = collect([$user])
                    ->concat(collect(PeopleMergeMap::MERGE[$user->id] ?? [])->map(fn (int $id) => $byId->get($id))->filter());

                $row = $this->personRow($user, $group, $userRoles, isset($activeContractorIds[$user->id]));

                $existingId = $this->map->newId('person', (int) $user->id);

                if ($existingId !== null) {
                    DB::table('people')->where('id', $existingId)->update($row);
                    $personId = $existingId;
                    $stats['updated']++;
                } else {
                    $personId = (int) DB::table('people')->insertGetId($row + ['created_at' => $user->created_at, 'updated_at' => $user->updated_at]);
                    $this->map->remember('person', (int) $user->id, $personId);
                    $stats['imported']++;
                }

                foreach (PeopleMergeMap::MERGE[$user->id] ?? [] as $loserId) {
                    if ($byId->has($loserId)) {
                        $this->map->remember('person', $loserId, $personId);
                        $stats['merged']++;
                    }
                }

                foreach ($this->newRoles($userRoles) as $roleName) {
                    if (isset($roleIds[$roleName])) {
                        $roleRows[] = ['role_id' => $roleIds[$roleName], 'model_type' => $morphClass, 'model_id' => $personId];
                    }
                }
            }

            if ($roleRows !== []) {
                $personIds = array_unique(array_column($roleRows, 'model_id'));
                DB::table('model_has_roles')->where('model_type', $morphClass)->whereIn('model_id', $personIds)->delete();
                DB::table('model_has_roles')->insertOrIgnore($roleRows);
            }
        });

        return $stats;
    }

    /**
     * @param  Collection<int, \stdClass>  $group  survivor first, then merged-away rows
     * @param  list<string>  $legacyRoles
     * @return array<string, mixed>
     */
    private function personRow(object $user, $group, array $legacyRoles, bool $hasActiveWorkOrder): array
    {
        $isContractor = in_array('contractor', $legacyRoles, true) || $legacyRoles === [];
        $isDeleted = $user->deleted_at !== null;

        $status = match (true) {
            $isContractor && ! $isDeleted && $hasActiveWorkOrder => PersonStatus::ContractorActive,
            $isContractor => PersonStatus::ContractorInactive,
            $isDeleted => PersonStatus::StaffInactive,
            default => PersonStatus::StaffActive,
        };

        $email = $group->pluck('email')->filter()->first();
        $phone = $group->pluck('phone')->filter()->first();
        $normalized = $group->pluck('normalized_phone')->filter()->first();

        return [
            'name' => trim($user->name),
            'email' => $email,
            'email_verified_at' => $user->email_verified_at,
            'phone' => $phone !== null ? mb_substr($phone, 0, 32) : null,
            'normalized_phone' => $normalized !== null ? mb_substr($normalized, 0, 15) : null,
            'password' => $user->password, // both apps hash with bcrypt — logins survive
            'status' => $status->value,
            'application_date' => Carbon::parse($user->created_at)->toDateString(),
            'converted_to_contractor_at' => $isContractor ? $user->created_at : null,
        ];
    }

    /**
     * @param  list<string>  $legacyRoles
     * @return list<string>
     */
    private function newRoles(array $legacyRoles): array
    {
        $mapped = [];

        foreach ($legacyRoles as $legacyRole) {
            $new = self::ROLE_MAP[$legacyRole] ?? null;

            if ($new !== null && ! in_array($new, $mapped, true)) {
                $mapped[] = $new;
            }
        }

        return $mapped;
    }

    /** @return array<int, list<string>> legacy user id => legacy role names */
    private function legacyRolesByUser(): array
    {
        return DB::connection('legacy')->table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_type', 'like', '%User')
            ->select('model_has_roles.model_id', 'roles.name')
            ->get()
            ->groupBy('model_id')
            ->map(fn ($rows) => $rows->pluck('name')->sort()->values()->all())
            ->all();
    }
}
