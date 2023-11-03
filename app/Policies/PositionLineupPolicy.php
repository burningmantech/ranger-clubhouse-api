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

    public function index(Person $user): false
    {
        return false;
    }

    public function show(Person $user, PositionLineup $positionLineup): false
    {
        return false;
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

    public function positions(Person $user, PositionLineup $positionLineup): false
    {
        return false;
    }
}
