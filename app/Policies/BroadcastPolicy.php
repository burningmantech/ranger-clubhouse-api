<?php

namespace App\Policies;

use App\Models\Broadcast;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class BroadcastPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /*
     * Can a user view their own messages?
     */

    public function messages(Person $user, $personId)
    {
        return ($user->id == $personId);
    }

    /*
     * Can a user see unverified stopped numbers
     */

    public function unverifiedStopped(Person $user): bool
    {
        return false;
    }

    /*
     * Can a user see the stats
     */

    public function stats(Person $user)
    {
        return false;
    }

    /*
     * Can a user retry a broadcast
     */

    public function retry(Person $user)
    {
        return false;
    }

    /*
     * Can a user see the unknown phones
     */

    public function unknownPhones(Person $user): bool
    {
        return false;
    }

    /*
     * Is the user allowed to interact with the broadcast type?
     */

    public function typeAllowed(Person $user, $type)
    {
        if ($user->hasRole(Role::MEGAPHONE)) {
            return true;
        }

        if ($user->hasRole(Role::EDIT_SLOTS)
            && ($type == Broadcast::TYPE_SLOT || $type == Broadcast::TYPE_SLOT_EDIT)) {
            return true;
        }

        return false;
    }

    /**
     * Can the person transmit?
     *
     * @param Person $user
     * @return bool
     */

    public function transmit(Person $user) : bool {
        return $user->hasRole(Role::MEGAPHONE);
    }
}
