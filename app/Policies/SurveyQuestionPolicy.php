<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use Illuminate\Auth\Access\HandlesAuthorization;

class SurveyQuestionPolicy
{
    use HandlesAuthorization;

    public function index(Person $user, Survey $survey): bool
    {
        return $survey->canManageSurvey($user);
    }

    public function show(Person $user, SurveyQuestion $surveyQuestion): bool
    {
        return $surveyQuestion->survey?->canManageSurvey($user);
    }

    /**
     * Determine whether the user can create a surveyQuestion document.
     */

    public function store(Person $user, SurveyQuestion $surveyQuestion): bool
    {
        return $surveyQuestion->survey?->canManageSurvey($user);
    }

    /**
     * Determine whether the user can update the survey question.
     */
    public function update(Person $user, SurveyQuestion $surveyQuestion): bool
    {
        return $surveyQuestion->survey?->canManageSurvey($user);
    }

    /**
     * Determine whether the user can delete the survey question.
     */
    public function destroy(Person $user, SurveyQuestion $surveyQuestion): bool
    {
        return $surveyQuestion->survey?->canManageSurvey($user);
    }
}
