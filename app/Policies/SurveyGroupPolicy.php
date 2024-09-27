<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Survey;
use App\Models\SurveyGroup;
use Illuminate\Auth\Access\HandlesAuthorization;

class SurveyGroupPolicy
{
    use HandlesAuthorization;

    public function index(Person $user, Survey $survey): bool
    {
        return $survey->canManageSurvey($user);
    }

    public function show(Person $user, SurveyGroup $surveyGroup): bool
    {
        return $surveyGroup->survey?->canManageSurvey($user);
    }

    /**
     * Determine whether the user can create a surveyGroup document.
     */

    public function store(Person $user, SurveyGroup $surveyGroup): bool
    {
        return $surveyGroup->survey?->canManageSurvey($user);
    }

    /**
     * Determine whether the user can update the surveyGroup.
     */
    public function update(Person $user, SurveyGroup $surveyGroup): bool
    {
        return $surveyGroup->survey?->canManageSurvey($user);
    }

    /**
     * Determine whether the user can delete the surveyGroup.
     */
    public function destroy(Person $user, SurveyGroup $surveyGroup): bool
    {
        return $surveyGroup->survey?->canManageSurvey($user);
    }
}
