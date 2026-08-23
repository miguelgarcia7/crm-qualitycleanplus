<?php

namespace App\Domain\People\Actions;

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * Creates a login for a property manager or internal staff member and emails
 * an invitation. The person is created without a password (auth columns are
 * nullable by design) and sets one through the emailed password-reset link —
 * we never handle or transmit a password ourselves. Property managers get
 * property assignments (what scopes QC Minute); recruiters may optionally get
 * a starting book of properties; staff roles get a hire date for PTO accrual.
 */
class InviteUser
{
    /**
     * @param  array{name: string, email: string, role: string, phone?: string|null, hire_date?: string|null, property_ids?: list<int>}  $data
     */
    public function handle(array $data): Person
    {
        $role = $data['role'];
        $isPropertyManager = $role === 'property_manager';

        $person = DB::transaction(function () use ($data, $role, $isPropertyManager): Person {
            $person = Person::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'normalized_phone' => isset($data['phone']) ? preg_replace('/\D/', '', $data['phone']) : null,
                'status' => PersonStatus::StaffActive,
                // Staff hire date drives PTO tiers/anniversaries; PMs are hotel
                // employees, not QCP staff, so they don't get one.
                'hire_date' => $isPropertyManager ? null : ($data['hire_date'] ?? now()->toDateString()),
                // The invite link only works from their inbox, which is the
                // same proof email verification would provide.
                'email_verified_at' => now(),
            ]);

            $person->syncRoles($role);

            $assignmentRole = $isPropertyManager ? PropertyAssignmentRole::PropertyManager : PropertyAssignmentRole::Recruiter;
            foreach (Property::query()->findMany($data['property_ids'] ?? []) as $property) {
                $property->assignments()->create([
                    'person_id' => $person->id,
                    'role' => $assignmentRole->value,
                ]);
            }

            return $person;
        });

        $person->notify(new UserInvitation(
            Password::broker()->createToken($person),
            $isPropertyManager ? 'qcminute' : 'backoffice',
        ));

        return $person;
    }
}
