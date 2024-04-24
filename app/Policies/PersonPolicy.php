<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class PersonPolicy
{
    use HandlesAuthorization;

    const array AUTHORIZED_ROLES = [
        Role::ADMIN,
        Role::ART_TRAINER,
        Role::MANAGE,
        Role::MENTOR,
        Role::TRAINER,
        Role::VC,
    ];

    /**
     * Can the user view a bunch of people?
     *
     * @param Person $user
     * @return bool
     */

    public function index(Person $user): bool
    {
        return $user->hasRole([Role::ADMIN, Role::VC]);
    }

    /**
     * Can the user search for people?
     *
     * @param Person $user
     * @return bool
     */

    public function search(Person $user): bool
    {
        return $user->hasRole(self::AUTHORIZED_ROLES);
    }

    /**
     * Can the user perform an advanced search for people?
     *
     * @param Person $user
     * @return bool
     */

    public function advancedSearch(Person $user): bool
    {
        return $user->hasRole([Role::ADMIN, Role::VC]);
    }

    /**
     * Determine whether the user can view the person.
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function view(Person $user, Person $person): bool
    {
        return (
            $person->id == $user->id ||
            $user->hasRole(self::AUTHORIZED_ROLES)
        );
    }

    /**
     * Determine whether the user can create people.
     */

    public function store(Person $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the person.
     *
     * @param Person $user
     * @param Person $person
     * @return bool|Response
     */
    public function update(Person $user, Person $person): bool|Response
    {
        /*
         * Do not allow a person to be updated when the status is problematic.
         */

        if (in_array($person->status, Person::LOCKED_STATUSES) && !$user->hasRole([Role::ADMIN, Role::MENTOR, Role::VC])) {
            return Response::deny('Person has a locked status. Only Admins, Mentors and VCs may update the record.');
        }

        if ($user->id == $person->id) {
            return true;
        }

        return $user->hasRole(self::AUTHORIZED_ROLES);
    }

    /**
     * Determine whether the user can delete the person.
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function delete(Person $user, Person $person): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can change the password
     *
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function password(Person $user, Person $person): bool
    {
        // check for admin is done above.
        return ($user->id == $person->id) || $user->isAdmin();
    }

    /**
     * pre-screen if the user can update the person's positions
     *
     * Note: with teams it may be possible for a team manager to grant the positions without
     * requiring additional roles.
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function updatePositions(Person $user, Person $person): bool
    {
        return $user->isAdmin() || $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine if the user can update the person's roles
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function updateRoles(Person $user, Person $person): bool
    {
        return $user->isAdmin();
    }

    public function updateTeams(Person $user, Person $person): bool
    {
        return $user->isAdmin() || $user->hasRole(Role::MANAGE);
    }

    public function mentees(Person $user, Person $person): bool
    {
        return ($user->id == $person->id || $user->hasRole(Role::MENTOR));
    }

    public function mentors(Person $user, Person $person): bool
    {
        return ($user->id == $person->id || $user->hasRole([Role::ADMIN, Role::VC, Role::MENTOR]));
    }

    public function eventInfo(Person $user, Person $person): bool
    {
        return $user->id == $person->id || $user->hasRole([Role::ADMIN, Role::MANAGE]);
    }

    public function alphaShirts(Person $user): bool
    {
        return $user->hasRole(Role::MANAGE);
    }

    public function peopleByLocation(Person $user): bool
    {
        return $user->hasRole([Role::ADMIN, Role::VIEW_PII, Role::MANAGE]);
    }

    public function peopleByStatus(Person $user): bool
    {
        return $user->hasRole([Role::ADMIN, Role::MANAGE]);
    }

    public function peopleByStatusChange(Person $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    public function statusHistory(Person $user): bool
    {
        return $user->hasRole(Role::MANAGE);
    }

    public function isAdmin(Person $user): bool
    {
        return $user->isAdmin();
    }

    public function bulkLookup(Person $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Can the person send a message to request the mailing lists to be updated?
     *
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function updateMailingLists(Person $user, Person $person): bool
    {
        return $user->id == $person->id || $user->hasRole([Role::ADMIN, Role::VC]);
    }

    /**
     * Can the user view the tickets & provisions progress?
     *
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function ticketsProvisionsProgress(Person $user, Person $person): bool
    {
        return ($user->id == $person->id) || $user->hasRole(Role::MANAGE);
    }

    /**
     * Can the user (re)send a Welcome Mail
     *
     * @param Person $user
     * @return bool
     */

    public function sendWelcomeEmail(Person $user): bool
    {
        return $user->hasRole([Role::ADMIN, Role::VC]);
    }

    /**
     * Can a user release this callsign?
     *
     */

    public function releaseCallsign(Person $user): bool
    {
        return $user->hasRole([Role::ADMIN, Role::VC]);
    }
}
