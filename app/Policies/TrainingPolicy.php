<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use App\Models\Training;

use Illuminate\Auth\Access\HandlesAuthorization;

class TrainingPolicy
{
    use HandlesAuthorization;

    public function before($user) : ?true
    {
        if ($user->hasRole([ Role::ADMIN, Role::TRAINER, Role::MENTOR, Role::VC ])) {
            return true;
        }

        return null;
    }

    /**
     * Can a user see the training and associated reports?
     */

    public function show(Person $user, Training $training): bool
    {
        if ($training->is_art) {
            return $user->hasRole(Role::ART_TRAINER_BASE | $training->id);
        }
        return false;
    }
}
