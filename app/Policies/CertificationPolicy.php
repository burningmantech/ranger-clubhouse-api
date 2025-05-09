<?php

namespace App\Policies;

use App\Models\Certification;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class CertificationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user see all the certifications
     *
     * @param Person $user
     * @return bool
     */

    public function index(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /**
     * Determine whether the user can view a certification
     */

    public function show(Person $user, Certification $certification): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create person vehicles.
     */

    public function store(Person $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can update the certification.
     */

    public function update(Person $user, Certification $certification): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can delete the person vehicle.
     */

    public function delete(Person $user, Certification $certification): bool
    {
        return $user->hasRole(Role::ADMIN);
    }
}
