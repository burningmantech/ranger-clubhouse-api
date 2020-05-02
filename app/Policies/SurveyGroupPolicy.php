<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\SurveyGroup;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class SurveyGroupPolicy
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
     * Determine whether the user can create a surveyGroup document.
     */

    public function store(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the surveyGroup.
     */
    public function update(Person $user, SurveyGroup $surveyGroup)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the surveyGroup.
     */
    public function destroy(Person $user, SurveyGroup $surveyGroup)
    {
        return false;
    }
}
