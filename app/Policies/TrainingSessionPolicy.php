<?php

namespace App\Policies;

use App\Models\TrainingSession;
use App\Models\Role;
use App\Models\Person;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainingSessionPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole([ Role::ADMIN, Role::TRAINER, Role::MENTOR, Role::VC ])) {
            return true;
        }
    }

    /**
     * Can a user see the training session(s)?
     */

    public function show(Person $user, TrainingSession $training_session)
    {
        return $this->checkForArt($user, $training_session);
    }

    /**
     *  Can the user score (mark passed, add notes, etc.) to a session?
     */

    public function score(Person $user, TrainingSession $training_session)
    {
        return $this->checkForArt($user, $training_session);
    }

    /**
     * Can the user add or remove a person to/from a session?
     *
     */

    public function admissions(Person $user, TrainingSession $training_session)
    {
        return $this->checkForArt($user, $training_session);
    }

    /**
     * Can the user set trainer status?
     *
     */

    public function trainerStatus(Person $user, TrainingSession $training_session)
    {
        return $this->checkForArt($user, $training_session);
    }

    private function checkForArt(Person $user, TrainingSession $training_session) {
        return ($training_session->isArt() && $user->hasRole(Role::ART_TRAINER));
    }
}
