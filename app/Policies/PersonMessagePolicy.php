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

    public function before(Person $user): ?true
    {
        if ($user->hasRole([Role::ADMIN, Role::VC, Role::MESSAGE_MANAGEMENT])) {
            return true;
        }
        
        return null;
    }

    /**
     * Can a person with either Event Management role be allowed to access messages? Only if EventManagementOnPlayaEnabled is true.
     * People with only Event Management and not Message Management are only allowed event access. 
     * 
     * @param Person $user
     * @return bool
     */

    private function isEventManagementAccessAllowed(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT) && setting('EventManagementOnPlayaEnabled');
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
        return ($this->isEventManagementAccessAllowed($user) || $user->id == $personId);
    }

    /**
     * Determine whether the user can store / send messages.
     *
     * @param Person $user
     * @return bool
     */

    public function store(Person $user): bool
    {
        return in_array($user->status, Person::ACTIVE_STATUSES);
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
        return ($this->isEventManagementAccessAllowed($user) || $user->id == $person_message->person_id);
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
        return $this->isEventManagementAccessAllowed($user) || ($user->id == $person_message->person_id);
    }
}
