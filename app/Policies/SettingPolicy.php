<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Setting;
use App\Models\Role;

use Illuminate\Auth\Access\HandlesAuthorization;

class SettingPolicy
{
    use HandlesAuthorization;

    public function before(Person $user) {
        if ($user->hasRole([ Role::ADMIN, Role::TECH_NINJA ])) {
            return true;
        }
    }

    public function index(Person $user)
    {
        return false;
    }

    public function show(Person $user, Setting $setting)
    {
        return false;
    }

    public function create(Person $user)
    {
        return false;
    }

    public function update(Person $user, Setting $setting)
    {
        return !$setting->is_credential || $user->hasRole(Role::TECH_NINJA);
    }

    public function destroy(Person $user, Setting $setting)
    {
        return false;
    }
}
