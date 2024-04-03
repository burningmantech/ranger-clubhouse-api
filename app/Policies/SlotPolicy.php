<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use App\Models\Slot;
use Illuminate\Auth\Access\HandlesAuthorization;

class SlotPolicy
{
    use HandlesAuthorization;

    public function before($user): ?bool
    {
        if ($user->hasRole([Role::ADMIN, Role::EDIT_SLOTS])) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view the slots.
     */
    public function index(Person $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the slots.
     */
    public function show(Person $user, Slot $slot): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create slots.
     */
    public function store(Person $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the slot.
     */
    public function update(Person $user, Slot $slot): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the slot.
     */
    public function delete(Person $user, Slot $slot): bool
    {
        return false;
    }

    /**
     * Determine if user can run various slot based reports
     */

    public function report(Person $user): bool
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Can the person link slots
     *
     * @param Person $user
     * @return false
     */

    public function linkSlots(Person $user): bool
    {
        return false;
    }
}
