<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonLanguage;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonLanguagePolicy
{
    use HandlesAuthorization;

    public function before($user): ?bool
    {
        if ($user->hasRole([Role::ADMIN, Role::VC])) {
            return true;
        }

        return null;
    }

    /**
     * Can the person see a list of records?
     */

    public function index(Person $user, $personId): bool
    {
        return $user->id == $personId || $user->hasRole(Role::VIEW_PII);
    }

    /**
     * Can the person create a language record
     *
     * @param Person $user
     * @param PersonLanguage $person_language
     * @return true
     */

    public function show(Person $user, PersonLanguage $person_language): true
    {
        return $user->id == $person_language->person_id || $user->hasRole(Role::VIEW_PII);
    }

    /**
     * Can the person create a language record
     *
     * @param Person $user
     * @return true
     */

    public function store(Person $user, PersonLanguage $person_language): true
    {
        return $user->id == $person_language->person_id;
    }

    /**
     * Determine whether the user can update the PersonLanguage.
     */

    public function update(Person $user, PersonLanguage $person_language): bool
    {
        return $user->id == $person_language->person_id;
    }

    /**
     * Determine whether the user can delete a language record
     */

    public function destroy(Person $user, PersonLanguage $person_language): bool
    {
        return $user->id == $person_language->person_id;
    }

    /**
     * Can the user run a search?
     */

    public function search(Person $user): bool
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Can the user run a search?
     */

    public function onSiteReport(Person $user): bool
    {
        return $user->hasRole(Role::MANAGE);
    }
}
