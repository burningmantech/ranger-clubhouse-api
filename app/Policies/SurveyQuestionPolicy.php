<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\SurveyQuestion;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class SurveyQuestionPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    public function index(Person $person) {
        return false;
    }

    public function show(Person $person) {
        return false;
    }

    /**
     * Determine whether the user can create a surveyQuestion document.
     */

    public function store(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the survey question.
     */
    public function update(Person $user, SurveyQuestion $surveyQuestion)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the survey question.
     */
    public function destroy(Person $user, SurveyQuestion $surveyQuestion)
    {
        return false;
    }
}
