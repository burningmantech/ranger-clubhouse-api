<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonMessage;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonMessagePolicy
{
    use HandlesAuthorization;
    
    /**
     * Allow ADMIN, VC or Message Management to do anything with messages
     */

    public function before(Person $user)
    {
        if ($user->hasRole([Role::ADMIN, Role::VC, Role::MESSAGE_MANAGEMENT])) {
            return true;
        }
    }

    /**
     * Can a person with LMOP/LMYR be allowed to access messages? Only if LoginManageOnPlayaEnabled is true.
     *
     * @param Person $user
     * @return bool
     */

    private function isLMAccessAllowed(Person $user): bool
    {
        return $user->hasRole(Role::MANAGE) && setting('LoginManageOnPlayaEnabled');
    }

    /**
     * Determine whether the user can view the message.
     *
     * @param Person $user
     * @param int|null $personId
     * @return mixed
     */

    public function index(Person $user, ?int $personId): bool
    {
        return ($this->isLMAccessAllowed($user) || $user->id == $personId);
    }

    /**
     * Determine whether the user can store / send messages.
     *
     * @param Person $user
     * @return bool
     */

    public function store(Person $user): bool
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine whether the user can delete a message.
     *
     * @param Person $user
     * @param PersonMessage $person_message
     * @return bool
     */

    public function delete(Person $user, PersonMessage $person_message): bool
    {
        return ($this->isLMAccessAllowed($user) || $user->id == $person_message->person_id);
    }

    /**
     * Determine whether the user can mark messages read.
     *
     * @param Person $user
     * @param PersonMessage $person_message
     * @return bool
     */

    public function markread(Person $user, PersonMessage $person_message): bool
    {
        return ($this->isLMAccessAllowed($user) || $user->id == $person_message->person_id);
    }
}
