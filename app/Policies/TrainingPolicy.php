<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use App\Models\Training;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainingPolicy
{
    use HandlesAuthorization;

    public function before(Person $user): ?true
    {
        if ($user->isAdmin()) {
            return true;
        }
        return null;
    }

    /**
     * Can a user see the training and associated reports?
     */

    public function show(Person $user, Training $training): bool
    {
        return $this->canView($user, $training);
    }

    private function canView(Person $user, Training $training): bool
    {
        if ($training->is_art) {
            return $user->hasRole(Role::ART_TRAINER_BASE | $training->id);
        } else if ($user->hasRole([Role::TRAINER, Role::MENTOR, Role::VC])) {
            return true;
        } else {
            return false;
        }
    }

    private function isARTTrainer(Person $user, Training $training): bool
    {
        if (!$training->is_art) {
            return false;
        }

        return $user->hasRole(Role::ART_TRAINER_BASE | $training->id);
    }

    public function multipleEnrollmentsReport(Person $user, Training $training): bool
    {
        return $this->canView($user, $training);
    }

    public function capacityReport(Person $user, Training $training): bool
    {
        return $this->canView($user, $training);
    }

    public function peopleTrainingCompleted(Person $user, Training $training): bool
    {
        return $this->canView($user, $training);
    }

    public function untrainedPeopleReport(Person $user, Training $training): bool
    {
        return $this->canView($user, $training);
    }

    public function trainedNoWorkReport(Person $user, Training $training): bool
    {
        return $this->canView($user, $training);
    }

    public function trainerAttendanceReport(Person $user, Training $training): bool
    {
        return $this->canView($user, $training);
    }

    public function mentees(Person $user, Training $training): bool
    {
        return $this->isARTTrainer($user, $training);
    }

    public function revokeMenteePositions(Person $user, Training $training): bool
    {
        if (!$training->is_art) {
            return false;
        }
        return $user->hasRole(Role::ART_GRADUATE_BASE | $training->id);
    }
}
