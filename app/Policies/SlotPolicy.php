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
     * Determine whether the user can view the slots.
     */
    public function index(Person $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the slots.
     */
    public function show(Person $user, Slot $slot)
    {
        return true;
    }

    /**
     * Determine whether the user can create slots.
     */
    public function store(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the slot.
     */
    public function update(Person $user, Slot $slot)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the slot.
     */
    public function delete(Person $user, Slot $slot)
    {
        return false;
    }

    /**
     * Determine if user can run various slot based reports
     */
    public function report(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }
}
