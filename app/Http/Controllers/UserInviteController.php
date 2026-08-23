<?php

namespace App\Http\Controllers;

use App\Domain\People\Actions\InviteUser;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Invite flow for new logins — property managers and internal staff. Creates
 * the person, assigns properties where the role calls for it, and emails a
 * set-your-password invitation. Routes gated `admin.users.create` (admin,
 * office manager, HR). Admin-tier roles are deliberately NOT invitable here —
 * granting ownership/developer access is not an onboarding-clerk capability.
 * One email = one identity across soft deletes (Phase 09e decision), hence
 * the whole-table unique rule.
 */
class UserInviteController extends Controller
{
    /** role => human label. Order is the dropdown order. */
    private const INVITABLE_ROLES = [
        'property_manager' => 'Property Manager',
        'recruiter' => 'Recruiter',
        'office_manager' => 'Office Manager',
        'front_desk' => 'Front Desk',
        'hr' => 'HR',
        'payroll' => 'Payroll',
        'w2_employee' => 'W-2 Employee',
    ];

    public function create(Request $request): Response
    {
        return Inertia::render('admin/people/invite', [
            'roles' => collect(self::INVITABLE_ROLES)->map(fn ($label, $value): array => ['value' => $value, 'label' => $label])->values(),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'preselectedPropertyId' => $request->integer('property_id') ?: null,
        ]);
    }

    public function store(Request $request, InviteUser $action): RedirectResponse
    {
        $role = (string) $request->input('role');
        $takesProperties = in_array($role, ['property_manager', 'recruiter'], true);

        /** @var array{name: string, email: string, role: string, phone: string|null, hire_date?: string|null, property_ids?: list<int>} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('people', 'email')],
            'role' => ['required', Rule::in(array_keys(self::INVITABLE_ROLES))],
            'phone' => ['nullable', 'string', 'max:32'],
            'hire_date' => $role === 'property_manager' ? ['prohibited'] : ['nullable', 'date'],
            'property_ids' => $takesProperties
                ? [$role === 'property_manager' ? 'required' : 'sometimes', 'array', ...($role === 'property_manager' ? ['min:1'] : [])]
                : ['prohibited'],
            'property_ids.*' => ['integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
        ]);

        $person = $action->handle($validated);

        return to_route('backoffice.people.index')
            ->with('success', "Invitation sent to {$person->email}.");
    }
}
