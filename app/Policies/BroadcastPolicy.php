<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

use App\Models\Broadcast;
use App\Models\Person;
use App\Models\Role;

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

    public function messages(Person $user, $personId) {
        return ($user->id == $personId);
    }
}
