<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use App\Models\SurveyGroup;
use Illuminate\Auth\Access\HandlesAuthorization;

class SurveyGroupPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole([Role::ADMIN, Role::SURVEY_MANAGEMENT])) {
            return true;
        }
    }

    public function index(Person $person): bool
    {
        return false;
    }

    public function show(Person $person): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create a surveyGroup document.
     */

    public function store(Person $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the surveyGroup.
     */
    public function update(Person $user, SurveyGroup $surveyGroup): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the surveyGroup.
     */
    public function destroy(Person $user, SurveyGroup $surveyGroup): bool
    {
        return false;
    }
}
