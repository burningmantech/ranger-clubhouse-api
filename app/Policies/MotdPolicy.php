<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class MotdPolicy
{
    use HandlesAuthorization;

    public function before(Person $user): ?true
    {
        if ($user->hasRole([Role::ADMIN, Role::ANNOUNCEMENT_MANAGEMENT])) {
            return true;
        }

        return null;
    }

    public function index(Person $user): false
    {
        return false;
    }

    public function bulletin(Person $user): true
    {
        return true;
    }

    public function show(Person $user, Motd $motd): false
    {
        return false;
    }

    public function create(Person $user): false
    {
        return false;
    }

    public function update(Person $user, Motd $motd): false
    {
        return false;
    }

    public function destroy(Person $user, Motd $motd): false
    {
        return false;
    }
}
