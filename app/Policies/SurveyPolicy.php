<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Position;
use App\Models\Role;
use App\Models\Survey;
use Illuminate\Auth\Access\HandlesAuthorization;

class SurveyPolicy
{
    use HandlesAuthorization;

    public function before($user): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function indexForPosition(Person $user, int $positionId): bool
    {
         return Survey::canManageSurveys($user, $positionId);
    }

    public function indexForAll(Person $user): bool
    {
        return $user->hasRole(Role::SURVEY_MANAGEMENT_TRAINING)
            || $user->hasAnyPositionRole(Role::SURVEY_MANAGEMENT_BASE);
    }

    public function positions(Person $user): bool
    {
        return $user->hasRole(Role::SURVEY_MANAGEMENT_TRAINING)
            || $user->hasAnyPositionRole(Role::SURVEY_MANAGEMENT_BASE);
    }

    public function show(Person $user, Survey $survey): bool
    {
        return $survey->canManageSurvey($user);
    }

    public function duplicate(Person $user, Survey $survey): bool
    {
        return $survey->canManageSurvey($user);
    }

    /**
     * Determine whether the user can create a survey document.
     */

    public function store(Person $user, Survey $survey): bool
    {
        return $survey->canManageSurvey($user);
    }

    /**
     * Determine whether the user can update the survey.
     */
    public function update(Person $user, Survey $survey): bool
    {
        return $survey->canManageSurvey($user);
    }

    /**
     * Determine whether the user can delete the survey.
     */

    public function destroy(Person $user, Survey $survey): bool
    {
        return $survey->canManageSurvey($user);
    }

    /**
     * Determine if the user can see all the responses
     */

    public function report(Person $user, Survey $survey): bool
    {
        if ($survey->canManageSurvey($user)) {
            return true;
        }

        if ($survey->position_id == Position::ALPHA) {
            return false;
        }

        return $survey->isTrainerForSurvey($user);
    }

    /**
     * Determine if a person can see the trainer's report.
     */

    public function trainerReport(Person $user, int $trainerId): bool
    {
        return ($user->id == $trainerId);
    }

    /**
     * Can the person see any survey results?
     *
     * @param Person $user
     * @param int $personId
     * @return bool
     */

    public function trainerSurveys(Person $user, int $personId): bool
    {
        return ($user->id == $personId);
    }

    public function allTrainersReport(Person $user, Survey $survey): bool
    {
        return $survey->canManageSurvey($user);
    }
}
