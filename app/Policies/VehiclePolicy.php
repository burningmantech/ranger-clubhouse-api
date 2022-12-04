<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\Role;
use App\Models\Vehicle;
use Illuminate\Auth\Access\HandlesAuthorization;

class VehiclePolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user see all the person vehicles
     *
     * @param Person $user
     * @return bool
     */

    public function index(Person $user): bool
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine whether the user see a specific person
     *
     * @param Person $user
     * @param $personId
     * @return bool
     */

    public function indexForPerson(Person $user, $personId): bool
    {
        return $user->hasRole(Role::MANAGE) || ($user->id == $personId);
    }

    /**
     * Determine whether the user can view the person vehicle.
     *
     * @param Person $user
     * @param Vehicle $vehicle
     * @return bool
     */

    public function show(Person $user, Vehicle $vehicle): bool
    {
        return $vehicle->person_id == $user->id || $user->hasRole([ Role::MANAGE, Role::VIEW_PII]);
    }

    /**
     * Determine whether the user can create person vehicles.
     *
     * @param Person $user
     * @return bool
     */

    public function store(Person $user): bool
    {
        return PersonEvent::isSet($user->id, 'may_request_stickers');
    }

    /**
     * Create the user create a vehicle request for themselves?
     *
     * @param Person $user
     * @param Vehicle $vehicle
     * @return bool
     */

    public function storeForPerson(Person $user, Vehicle $vehicle): bool
    {
        return PersonEvent::isSet($user->id, 'may_request_stickers') && $vehicle->person_id == $user->id;
    }

    /**
     * Determine whether the user can update the asset.
     *
     * @param Person $user
     * @param Vehicle $vehicle
     * @return bool
     */
    public function update(Person $user, Vehicle $vehicle): bool
    {
        return ($vehicle->person_id == $user->id) || $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine whether the user can delete the person vehicle.
     *
     * @param Person $user
     * @param Vehicle $vehicle
     * @return bool
     */
    public function delete(Person $user, Vehicle $vehicle): bool
    {
        return ($vehicle->person_id == $user->id && $vehicle->status == Vehicle::PENDING);
    }

    /**
     * Can the user run the paperwork report?
     *
     * @param Person $user
     * @return bool
     */

    public function paperwork(Person $user): bool
    {
        return $user->hasRole([ Role::ADMIN, Role::MANAGE ]);
    }
}
