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

    public function messages(Person $user, $personId): bool
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

    public function stats(Person $user): false
    {
        return false;
    }

    /*
     * Can a user retry a broadcast
     */

    public function retry(Person $user): false
    {
        return false;
    }

    /*
     * Can a user see the unknown phones
     */

    public function unknownPhones(Person $user): false
    {
        return false;
    }

    public function details(Person $user): bool
    {
        return $user->hasRole([Role::MEGAPHONE, Role::MEGAPHONE_TEAM_ONPLAYA, Role::MEGAPHONE_EMERGENCY_ONPLAYA]);
    }

    /*
     * Is the user allowed to interact with the broadcast type?
     */

    public function typeAllowed(Person $user, $type): bool
    {
        if ($type == Broadcast::TYPE_EMERGENCY) {
            return $user->hasRole(Role::MEGAPHONE_EMERGENCY_ONPLAYA);
        }

        return $user->hasRole([Role::MEGAPHONE, Role::MEGAPHONE_TEAM_ONPLAYA, Role::MEGAPHONE_EMERGENCY_ONPLAYA]);
    }

    /**
     * Can the person transmit?
     *
     * @param Person $user
     * @return bool
     */

    public function transmit(Person $user): bool
    {
        return $user->hasRole([Role::MEGAPHONE, Role::MEGAPHONE_TEAM_ONPLAYA, Role::MEGAPHONE_EMERGENCY_ONPLAYA]);
    }
}
