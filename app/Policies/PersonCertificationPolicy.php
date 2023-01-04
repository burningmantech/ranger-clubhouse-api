<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonCertification;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonCertificationPolicy
{
    use HandlesAuthorization;

    public function peopleReport(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine whether the user see all the certifications
     *
     * @param Person $user
     * @param $personId
     * @return bool
     */

    public function index(Person $user, $personId): bool
    {
        return $personId == $user->id || $user->hasRole([Role::CERTIFICATION_MGMT, Role::MANAGE]);
    }

    /**
     * Determine whether the user can view a certification
     */

    public function show(Person $user, PersonCertification $certification): bool
    {
        return $user->id == $certification->person_id || $user->hasRole([Role::CERTIFICATION_MGMT, Role::MANAGE]);
    }

    /**
     * Determine whether the user can update a person certification.
     */

    public function store(Person $user, $personId): bool
    {
        return $user->id == $personId || $user->hasRole([Role::CERTIFICATION_MGMT, Role::ADMIN]);
    }

    /**
     * Determine whether the user can update a person certification.
     */

    public function update(Person $user, PersonCertification $certification): bool
    {
        return ($user->id == $certification->person_id) || $user->hasRole([Role::CERTIFICATION_MGMT, Role::ADMIN]);
    }

    /**
     * Determine whether the user can update the certification.
     */

    public function destroy(Person $user, PersonCertification $certification): bool
    {
        return ($user->id == $certification->person_id) || $user->hasRole([Role::CERTIFICATION_MGMT, Role::ADMIN]);
    }
}
