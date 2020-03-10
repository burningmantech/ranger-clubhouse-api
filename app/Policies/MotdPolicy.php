<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Setting;
use App\Models\Role;

use Illuminate\Auth\Access\HandlesAuthorization;

class MotdPolicy
{
    use HandlesAuthorization;

    public function before(Person $user) {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    public function index(Person $user)
    {
        return false;
    }

    public function bulletin(Person $user) {
        return true;
    }

    public function show(Person $user, Motd $motd)
    {
        return false;
    }

    public function create(Person $user)
    {
        return false;
    }

    public function update(Person $user, Motd $motd)
    {
        return false;
    }

    public function destroy(Person $user, Motd $motd)
    {
        return false;
    }
}
