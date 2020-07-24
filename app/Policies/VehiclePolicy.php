<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Vehicle;
use App\Models\PersonEvent;
use App\Models\Role;

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
     */
    public function index(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine whether the user see a specific person
     */

    public function indexForPerson(Person $user, $personId)
    {
        return $user->hasRole(Role::MANAGE) || ($user->id == $personId);
    }

    /**
     * Determine whether the user can view the person vehicle.
     */
    public function show(Person $user, Vehicle $vehicle)
    {
        return $vehicle->person_id == $user->id || $user->hasRole([ Role::MANAGE, Role::VIEW_PII]);
    }

    /**
     * Determine whether the user can create person vehicles.
     *
     */

    public function store(Person $user)
    {
        return PersonEvent::mayRequestStickersForYear($user->id, current_year());
    }

    public function storeForPerson(Person $user, Vehicle $vehicle)
    {
        return PersonEvent::mayRequestStickersForYear($user->id, current_year())
            && $vehicle->person_id == $user->id;
    }
    /**
     * Determine whether the user can update the asset.
     *
     */
    public function update(Person $user, Vehicle $vehicle)
    {
        return ($vehicle->person_id == $user->id) || $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine whether the user can delete the person vehicle.
     *
     */
    public function delete(Person $user, Vehicle $vehicle)
    {
        return ($vehicle->person_id == $user->id && $vehicle->status == Vehicle::PENDING);
    }
 }
