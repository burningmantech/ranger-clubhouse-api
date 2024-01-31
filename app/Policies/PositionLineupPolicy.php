<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PositionLineup;
use App\Models\Role;

class PositionLineupPolicy
{

    public function before(Person $user)
    {
        if ($user->hasRole(Role::TECH_NINJA)) {
            return true;
        }
    }

    public function index(Person $user): bool
    {
        return $user->hasRole(Role::MANAGE);
    }

    public function show(Person $user, PositionLineup $positionLineup): bool
    {
        return $user->hasRole(Role::MANAGE);
    }

    public function store(Person $user): false
    {
        return false;
    }

    public function update(Person $user, PositionLineup $positionLineup): false
    {
        return false;
    }

    public function destroy(Person $user, PositionLineup $positionLineup): false
    {
        return false;
    }

    public function positions(Person $user, PositionLineup $positionLineup): bool
    {
        return $user->hasRole(Role::MANAGE);
    }
}
