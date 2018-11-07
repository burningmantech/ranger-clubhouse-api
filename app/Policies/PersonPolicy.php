<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;

use Illuminate\Auth\Access\HandlesAuthorization;

class PersonPolicy
{
    use HandlesAuthorization;

    public function before($user, $ability)
    {
        if ($user->hasRole(Role::ADMIN) && $ability != 'contact') {
            return true;
        }
    }

    /**
     * Determine whether the user can view the person.
     *
     * @param  \App\Models\Person $user
     * @param  \App\Person        $person
     * @return mixed
     */
    public function view(Person $person)
    {
        return true;
    }

    /**
     * Determine whether the user can create people.
     *
     * @param  \App\Models\Person $user
     * @return mixed
     */
    public function create(Person $user)
    {
        return false;
        //
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

        return $user->hasRole([Role::TRAINER, Role::MENTOR, Role::VC]);
    }

    /**
     * Determine whether the user can delete the person.
     *
     * @param  \App\Models\Person $user
     * @param  \App\Person        $person
     * @return mixed
     */
    public function delete(Person $user, Person $person)
    {
        return false;
    }

    /*
     * Determine if the user can change the password
     *
     * @param \App\Person $person
     */

    public function password(Person $user, Person $person)
    {
        // check for admin is done above.
        return ($user->id == $person->id);
    }

     /*
      * Determine if the user can update the person's positions
      */

    public function updatePositions(Person $user, Person $person)
    {
        return $user->hasRole(Role::GRANT_POSITION);
    }

      /*
       * Determine if the user can update the person's roles
       */

    public function updateRoles(Person $user, Person $person)
    {
         // admin check is done in the before() function
        return false;
    }

    public function mentees(Person $user, Person $person)
    {
        return ($user->id == $person->id || $user->hasRole(Role::MENTOR));
    }

}
