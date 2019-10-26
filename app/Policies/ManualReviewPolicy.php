<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\ManualReview;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class ManualReviewPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the manualReview.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\App\Models\ManualReview  $manualReview
     * @return mixed
     */
    public function view(Person $user, ManualReview $manualReview)
    {
        return false;
    }

    /**
     * Determine whether the user can create manualReviews.
     *
     * @param  \App\Models\Person  $user
     * @return mixed
     */
    public function store(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the manualReview.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\App\Models\ManualReview  $manualReview
     * @return mixed
     */
    public function update(Person $user, ManualReview $manualReview)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the manualReview.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\App\Models\ManualReview  $manualReview
     * @return mixed
     */
    public function delete(Person $user, ManualReview $manualReview)
    {
        return false;
    }

    /*
     * Can the user import the manual review spreadsheet?
     */

    public function import(Person $user)
    {
        return false; // only admins
    }

    /*
     * Can the user see the manual review configuration?
     */

    public function config(Person $user)
    {
        return false; // only admins
    }

    /*
     * Can the user see the raw spreadsheet
     */

    public function spreadsheet(Person $user)
    {
        return false; // only admins
    }
}
