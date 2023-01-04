<?php

namespace App\Policies;

use App\Models\HandleReservation;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class HandleReservationPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN) || $user->hasRole(Role::VC)) {
            return true;
        }
    }

    /**
     * Determine whether the user can view any models.
     *
     * @param Person $person
     * @return bool
     */
    public function viewAny(Person $person): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param Person $person
     * @param HandleReservation $handleReservation
     * @return bool
     */
    public function view(Person $person, HandleReservation $handleReservation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param Person $person
     * @return bool
     */
    public function create(Person $person): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param Person $person
     * @param HandleReservation $handleReservation
     * @return bool
     */
    public function update(Person $person, HandleReservation $handleReservation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param Person $person
     * @param HandleReservation $handleReservation
     * @return bool
     */
    public function delete(Person $person, HandleReservation $handleReservation): bool
    {
        return false;
    }
}
