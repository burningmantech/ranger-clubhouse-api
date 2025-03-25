<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PodPolicy
{
    use HandlesAuthorization;

    public function before(Person $user): bool|null
    {
        if ($user->hasRole([Role::ADMIN, Role::MENTOR, Role::POD_MANAGEMENT])) {
            return true;
        }

        return null;
    }

    public function index(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    public function show(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    public function store(Person $user): bool
    {
        return false;
    }

    public function createAlphaSet(Person $user): bool
    {
        return false;
    }

    public function update(Person $user): bool
    {
        return false;
    }

    public function destroy(Person $user): bool
    {
        return false;
    }

    public function addPerson(Person $user): bool
    {
        return false;
    }

    public function removePerson(Person $user): bool
    {
        return false;
    }

    public function updatePerson(Person $user): bool
    {
        return false;
    }
}