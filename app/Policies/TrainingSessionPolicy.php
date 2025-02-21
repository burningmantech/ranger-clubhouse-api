<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use App\Models\TrainingSession;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainingSessionPolicy
{
    use HandlesAuthorization;

    public function before(Person $user) : ?true
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Can a user see the training session(s)?
     */

    public function show(Person $user, TrainingSession $training_session): bool
    {
        return $this->isAllowedForSession($user, $training_session, true);
    }

    /**
     *  Can the user score (mark passed, add notes, etc.) to a session?
     */

    public function score(Person $user, TrainingSession $training_session): bool
    {
        return $this->isAllowedForSession($user, $training_session);
    }

    /**
     * Can the user add or remove a person to/from a session?
     *
     */

    public function admissions(Person $user, TrainingSession $training_session): bool
    {
        return $this->isAllowedForSession($user, $training_session);
    }

    /**
     * Can the user set trainer status?
     *
     */

    public function trainerStatus(Person $user, TrainingSession $training_session): bool
    {
        return $this->isAllowedForSession($user, $training_session);
    }

    public function trainers(Person $user, TrainingSession $training_session): bool
    {
        return $this->isAllowedForSession($user, $training_session);
    }

    public function graduationCandidates(Person $user, TrainingSession $training_session): bool
    {
        return $this->isAllowedForSession($user, $training_session);
    }

    public function graduateCandidates(Person $user, TrainingSession $training_session): bool
    {
        if (!$training_session->isArt()) {
            // Only ARTs have graduations.
            return false;
        }

        return $user->hasRole(Role::ART_GRADUATE_BASE | $training_session->position_id);
    }

    private function isAllowedForSession(Person $user, TrainingSession $training_session, bool $inTakeAllow = false): bool
    {
        if ($training_session->isArt()) {
            return $user->hasRole(Role::ART_TRAINER_BASE | $training_session->position_id);
        }

        if ($inTakeAllow && $user->hasRole([Role::MENTOR, Role::VC])) {
            return true;
        }

        return $user->hasRole(Role::TRAINER);
    }
}
