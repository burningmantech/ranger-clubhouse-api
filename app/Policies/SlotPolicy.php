<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Slot;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class SlotPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole([Role::ADMIN, Role::EDIT_SLOTS])) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the position.
     */
    public function view(Person $user, Slot $slot)
    {
        return true;
    }

    /**
     * Determine whether the user can create positions.
     */
    public function store(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the position.
     */
    public function update(Person $user, Slot $slot)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the position.
     */
    public function delete(Person $user, Slot $slot)
    {
        return false;
    }
}
