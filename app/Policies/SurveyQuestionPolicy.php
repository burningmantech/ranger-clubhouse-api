<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use App\Models\SurveyQuestion;
use Illuminate\Auth\Access\HandlesAuthorization;

class SurveyQuestionPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole([ Role::ADMIN, Role::SURVEY_MANAGEMENT ])) {
            return true;
        }
    }

    public function index(Person $person) : bool
    {
        return false;
    }

    public function show(Person $person) : bool
    {
        return false;
    }

    /**
     * Determine whether the user can create a surveyQuestion document.
     */

    public function store(Person $user) : bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the survey question.
     */
    public function update(Person $user, SurveyQuestion $surveyQuestion) : bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the survey question.
     */
    public function destroy(Person $user, SurveyQuestion $surveyQuestion) : bool
    {
        return false;
    }
}
