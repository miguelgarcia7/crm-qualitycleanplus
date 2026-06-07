<?php

namespace App\Domain\PropertyBible\Policies;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Contract;

/**
 * Contracts are sensitive: only roles holding the seeded `bible.contracts.*`
 * permissions (ownership + payroll) may view, edit, or download them.
 * See 20-domain/property-bible.md §4. No "(own)" scoping — those roles are global.
 */
class ContractPolicy
{
    public function viewAny(Person $user): bool
    {
        return $user->can('bible.contracts.view');
    }

    public function view(Person $user, Contract $contract): bool
    {
        return $user->can('bible.contracts.view');
    }

    public function create(Person $user): bool
    {
        return $user->can('bible.contracts.edit');
    }

    public function update(Person $user, Contract $contract): bool
    {
        return $user->can('bible.contracts.edit');
    }

    public function delete(Person $user, Contract $contract): bool
    {
        return $user->can('bible.contracts.edit');
    }

    public function download(Person $user, Contract $contract): bool
    {
        return $user->can('bible.contracts.download');
    }
}
