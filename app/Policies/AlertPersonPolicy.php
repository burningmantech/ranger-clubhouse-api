<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class AlertPersonPolicy
{
    use HandlesAuthorization;

    public function before($user): ?bool
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view alerts
     */
    public function view(Person $user, Person $person): bool
    {
        return ($user->id == $person->id);
    }

    /**
     * Determine whether the user can update the alert
     */
    public function update(Person $user, Person $person): bool
    {
        return ($user->id == $person->id);
    }
}
