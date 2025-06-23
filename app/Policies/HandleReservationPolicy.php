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
        if ($user->hasRole([Role::ADMIN, Role::EDIT_HANDLE_RESERVATIONS])) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view all the records
     *
     * @param Person $user
     * @return bool
     */
    public function index(Person $user): bool
    {
        return $user->hasRole(Role::VC);
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param Person $user
     * @param HandleReservation $handleReservation
     * @return bool
     */

    public function show(Person $user, HandleReservation $handleReservation): bool
    {
        return $user->hasRole(Role::VC);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param Person $user
     * @return bool
     */
    public function store(Person $user): false
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
    public function destroy(Person $user, HandleReservation $handleReservation): false
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

    public function handles(Person $user): bool
    {
        return $user->hasRole(Role::VC);
    }
}
