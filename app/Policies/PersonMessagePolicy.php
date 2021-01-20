<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use App\Models\PersonMessage;

use Illuminate\Auth\Access\HandlesAuthorization;

class PersonMessagePolicy
{
    use HandlesAuthorization;

    protected $user;

    /*
     * Allow ADMIN or MANAGE (Ranger HQ) to do anything with messages
     */

    public function before(Person $user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    private function isLMOPEnabled($user) {
        return $user->hasRole(Role::MANAGE) && setting('LoginManageOnPlayaEnabled');
    }

    /**
     * Determine whether the user can view the message.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\Models\Person  $person
     * @return mixed
     */
    public function index(Person $user, $personId)
    {
        return ($this->isLMOPEnabled($user) || $user->id == $personId);
    }

    /**
     * Determine whether the user can store messages.
     *
     * @param  \App\Models\Person  $user
     * @return mixed
     */

    public function store(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can delete a message.
     *
     * @param  \App\Models\Person  $user
     * @return mixed
     */

    public function delete(Person $user, PersonMessage $person_message)
    {
        return ($this->isLMOPEnabled($user) || $user->id == $person_message->person_id);
    }

    /**
     * Determine whether the user can mark messages read.
     *
     * @param  \App\Models\Person  $user
     * @return mixed
     */

    public function markread(Person $user, PersonMessage $person_message)
    {
        return ($this->isLMOPEnabled($user) || $user->id == $person_message->person_id);
    }
}
