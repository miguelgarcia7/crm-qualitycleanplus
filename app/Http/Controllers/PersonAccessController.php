<?php

namespace App\Http\Controllers;

use App\Domain\People\Actions\ReassignContractors;
use App\Domain\People\Actions\UpdatePersonRoles;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Support\StaffRoles;
use App\Notifications\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

/**
 * Setting up a person after the invite, from their page: which office roles
 * they hold, a fresh invitation when the first one lapsed, and whose
 * contractors are whose.
 *
 * Roles and invitations: whoever can invite (admin.users.create). Never your
 * own roles, and never an admin's or a property manager's. Moving
 * contractors: people.contractors.reassign; a recruiter only moves their own.
 */
class PersonAccessController extends Controller
{
    /** Roles that may move anyone's contractors; recruiters move only their own. */
    private const MOVE_ANYONE = ['super_admin', 'admin', 'office_manager', 'hr'];

    public function updateRoles(Request $request, Person $person, UpdatePersonRoles $action): RedirectResponse
    {
        /** @var Person $user */
        $user = $request->user();
        abort_unless(self::canChangeRoles($user, $person), 403);

        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(array_keys(StaffRoles::LABELS))],
        ]);

        $action->handle($person, array_values(array_unique($validated['roles'])), $user);

        return back()->with('success', "Roles updated for {$person->name}.");
    }

    public function resendInvitation(Request $request, Person $person): RedirectResponse
    {
        /** @var Person $user */
        $user = $request->user();
        abort_unless($user->can('admin.users.create') && $user->id !== $person->id, 403);
        abort_unless($person->password === null && $person->status === PersonStatus::StaffActive, 422, 'This person has already set a password.');

        $person->notify(new UserInvitation(
            Password::broker('invites')->createToken($person),
            $person->hasRole('property_manager') ? 'qcminute' : 'backoffice',
        ));

        activity()->performedOn($person)->causedBy($user)->event('invitation_sent')->log('Sent a new invitation');

        return back()->with('success', "A new invitation was sent to {$person->email}.");
    }

    public function moveContractors(Request $request, Person $person, ReassignContractors $action): RedirectResponse
    {
        /** @var Person $user */
        $user = $request->user();
        abort_unless(self::canMoveFrom($user, $person), 403);

        $validated = $request->validate([
            'to' => ['required', 'integer', Rule::notIn([$person->id]), Rule::in(self::recruiterIds())],
            'contractor_ids' => ['required', 'array', 'min:1'],
            'contractor_ids.*' => ['integer', Rule::exists('people', 'id')->where('primary_recruiter_id', $person->id)],
            'give_properties' => ['boolean'],
            'remove_properties' => ['boolean'],
        ]);

        $to = Person::findOrFail($validated['to']);
        $moved = $action->handle(
            $person,
            $to,
            array_map('intval', $validated['contractor_ids']),
            $user,
            (bool) ($validated['give_properties'] ?? false),
            (bool) ($validated['remove_properties'] ?? false),
        );

        return back()->with('success', "Moved {$moved} ".($moved === 1 ? 'contractor' : 'contractors')." to {$to->name}.");
    }

    public function changeRecruiter(Request $request, Person $person, ReassignContractors $action): RedirectResponse
    {
        /** @var Person $user */
        $user = $request->user();
        abort_unless($person->status->isContractor() && self::canMoveContractor($user, $person), 403);

        $validated = $request->validate([
            'to' => ['required', 'integer', Rule::notIn(array_filter([$person->primary_recruiter_id])), Rule::in(self::recruiterIds())],
        ]);

        $to = Person::findOrFail($validated['to']);
        $action->handle($person->primaryRecruiter, $to, [$person->id], $user);

        return back()->with('success', "{$person->name}'s recruiter is now {$to->name}.");
    }

    public static function canChangeRoles(Person $user, Person $person): bool
    {
        return $user->can('admin.users.create')
            && $user->id !== $person->id
            && $person->status->isStaff()
            && ! $person->hasAnyRole(StaffRoles::NOT_EDITABLE);
    }

    public static function canMoveFrom(Person $user, Person $recruiter): bool
    {
        return $user->can('people.contractors.reassign')
            && ($user->hasAnyRole(self::MOVE_ANYONE) || $user->id === $recruiter->id);
    }

    public static function canMoveContractor(Person $user, Person $contractor): bool
    {
        return $user->can('people.contractors.reassign')
            && ($user->hasAnyRole(self::MOVE_ANYONE) || $contractor->primary_recruiter_id === $user->id);
    }

    /** @return list<int> active recruiters, who can take contractors */
    public static function recruiterIds(): array
    {
        return Person::role('recruiter')->where('status', PersonStatus::StaffActive->value)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
