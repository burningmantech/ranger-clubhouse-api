<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Position;
use App\Models\Survey;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class SurveyPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole([ Role::SURVEY_MANAGEMENT, Role::ADMIN ])) {
            return true;
        }
    }

    public function index(Person $user): false
    {
        return false;
    }

    public function show(Person $user): false
    {
        return false;
    }

    public function duplicate(Person $user, Survey $survey): false
    {
        return false;
    }

    /**
     * Determine whether the user can create a survey document.
     */

    public function store(Person $user): false
    {
        return false;
    }

    /**
     * Determine whether the user can update the survey.
     */
    public function update(Person $user, Survey $survey): false
    {
        return false;
    }

    /**
     * Determine whether the user can delete the survey.
     */

    public function destroy(Person $user, Survey $survey): false
    {
        return false;
    }

    /**
     * Determine if the user can see the responses
     */

    public function report(Person $user, Survey $survey, int $trainerId): bool
    {
        return ($user->hasRole(($survey->position_id == Position::TRAINING) ? Role::TRAINER : Role::ART_TRAINER));
    }

    /**
     * Determine if a person can see the trainer's report.
     */

    public function trainerReport(Person $user, int $trainerId): bool
    {
        return ($user->id == $trainerId);
    }

    public function trainerSurveys(Person $user, int $personId): bool
    {
        return ($user->id == $personId);
    }

    public function allTrainersReport(Person $user, Survey $survey): false
    {
        return false;
    }
}
