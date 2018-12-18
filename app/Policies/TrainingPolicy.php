<?php

namespace App\Policies;

use App\Models\Training;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainingPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole([ Role::ADMIN, Role::TRAINER, Role::MENTOR, Role::VC ])) {
            return true;
        }
    }

    /**
     * Can a user see the training and associated reports?
     */

    public function show(Person $user)
    {
        return false;
    }
}
