<?php

namespace App\Policies;

use App\Models\HandleReservation;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class HandleReservationPolicy
{
    use HandlesAuthorization;

    public function before($user): ?bool
    {
        if ($user->hasRole([Role::ADMIN, Role::VC])) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     *
     * @param Person $user
     * @return bool
     */
    public function viewAny(Person $user): false
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param Person $user
     * @param HandleReservation $handleReservation
     * @return bool
     */
    public function view(Person $user, HandleReservation $handleReservation): false
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param Person $user
     * @return bool
     */
    public function create(Person $user): false
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param Person $user
     * @param HandleReservation $handleReservation
     * @return bool
     */
    public function update(Person $user, HandleReservation $handleReservation): false
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param Person $user
     * @param HandleReservation $handleReservation
     * @return bool
     */
    public function delete(Person $user, HandleReservation $handleReservation): false
    {
        return false;
    }

    /**
     * Determine if the user can bulk upload handles
     */

    public function upload(Person $user): false
    {
        return false;
    }

    /**
     * Determine if the user can expire the handles
     */

    public function expire(Person $user): false
    {
        return false;
    }

    /**
     * Can the person retrieve all the handles and callsigns?
     *
     * @param Person $user
     * @return false
     */

    public function handles(Person $user): false
    {
        return false;
    }
}
