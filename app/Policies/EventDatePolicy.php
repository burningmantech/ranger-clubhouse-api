<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\EventDate;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventDatePolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the event dates.
     */
    public function index(Person $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the event dates.
     */
    public function show(Person $user, EventDate $event_date)
    {
        return true;
    }

    /**
     * Determine whether the user can create event dates.
     */
    public function store(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the event date.
     */
    public function update(Person $user, EventDate $event_date)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the event date.
     */
    public function delete(Person $user, EventDate $event_date)
    {
        return false;
    }
}
