<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the role.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\App\Models\Role  $role
     * @return mixed
     */
    public function view(Person $user, Role $role)
    {
        return true;
    }

    /**
     * Determine whether the user can create roles.
     *
     * @param  \App\Models\Person  $user
     * @return mixed
     */
    public function store(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the role.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\App\Models\Role  $role
     * @return mixed
     */
    public function update(Person $user, Role $role)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the role.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\App\Models\Role  $role
     * @return mixed
     */
    public function delete(Person $user, Role $role)
    {
        return false;
    }
}
