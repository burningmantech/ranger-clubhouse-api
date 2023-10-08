<?php

namespace App\Policies;

use App\Models\OnlineCourse;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class OnlineCoursePolicy
{
    use HandlesAuthorization;

    public function before($user) : ?bool
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can create an online course.
     */

    public function index(Person $user): bool
    {
        return $user->hasRole(Role::TRAINER);
    }

    /**
     * Determine whether the user can create an online course.
     */

    public function store(Person $user): false
    {
        return false;
    }

    /**
     * Determine whether the user can update an online course record.
     */

    public function update(Person $user, OnlineCourse $onlineCourse): false
    {
        return false;
    }

    /**
     * Determine whether the user can delete an online course record.
     */
    public function destroy(Person $user, OnlineCourse $onlineCourse): false
    {
        return false;
    }

    /**
     * Determine if the user can set the name from the LMS.
     */

    public function setName(Person $user, OnlineCourse $onlineCourse) : false
    {
        return false;
    }


    /**
     * Can the person run the progress report?
     *
     * @param Person $user
     * @return bool
     */

    public function progressReport(Person $user) : bool
    {
        return $user->hasRole(Role::TRAINER);
    }

    /**
     * Can the person run the enrollment report?
     *
     * @param Person $user
     * @param OnlineCourse $onlineCourse
     * @return bool
     */

    public function enrollment(Person $user, OnlineCourse $onlineCourse) : bool
    {
        return $user->hasRole(Role::TRAINER);
    }

    /**
     * Can the person retrieve the Moodle courses?
     */

    public function courses(Person $user) : false
    {
        return false;
    }
}
