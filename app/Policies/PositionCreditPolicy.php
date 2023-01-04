<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PositionCredit;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PositionCreditPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the position credit.
     */

    public function view(Person $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create position credit.
     *
     * @param  \App\Models\Person  $user
     * @return mixed
     */

    public function store(Person $user) : bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the position.
     *
     * @param Person $user
     * @param PositionCredit $position_credit
     * @return bool
     */

    public function update(Person $user, PositionCredit $position_credit): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the position.
     */

    public function delete(Person $user, PositionCredit $position_credit): bool
    {
        return false;
    }
}
