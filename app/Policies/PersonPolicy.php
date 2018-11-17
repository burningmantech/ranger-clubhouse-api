<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;

use Illuminate\Auth\Access\HandlesAuthorization;

class PersonPolicy
{
    use HandlesAuthorization;

    private $authorizedRoles = [
        Role::ADMIN, Role::MANAGE, Role::VC, Role::MENTOR, Role::TRAINER
    ];

    /*
     * Determine whether the user can view the person.
     *
     */
    public function view(Person $user, Person $person)
    {
        return (
            $person->id == $user->id ||
            $user->hasRole($this->authorizedRoles)
        );
    }

    /*
     * Determine whether the user can create people.
     */
    public function create(Person $user)
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the person.
     *
     * @param  \App\Models\Person $user
     * @param  \App\Person        $person
     * @return mixed
     */
    public function update(Person $user, Person $person)
    {
        if ($user->id == $person->id) {
            return true;
        }

        return $user->hasRole($this->authorizedRoles);
    }

    /*
     * Determine whether the user can delete the person.
     *
     */
    public function delete(Person $user, Person $person)
    {
        return $user->isAdmin();
    }

    /*
     * Determine if the user can change the password
     *
     */

    public function password(Person $user, Person $person)
    {
        // check for admin is done above.
        return ($user->id == $person->id) || $user->isAdmin();
    }

    /*
     * Determine if the user can update the person's positions
     */

    public function updatePositions(Person $user, Person $person)
    {
        return $user->hasRole(Role::GRANT_POSITION) && $user->hasRole(Role::MANAGE);
    }

    /*
     * Determine if the user can update the person's roles
     */

    public function updateRoles(Person $user, Person $person)
    {
        return $user->isAdmin();
    }

    public function mentees(Person $user, Person $person)
    {
        return ($user->id == $person->id || $user->hasRole(Role::MENTOR));
    }
}
