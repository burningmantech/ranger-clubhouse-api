<?php

namespace App\Policies;

use App\Models\Bmid;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class BmidPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole([Role::ADMIN, Role::EDIT_BMIDS])) {
            return true;
        }
    }

    /**
     * Determine whether the user see all the BMIDs
     */

    public function index(Person $user): bool
    {
        return $user->hasRole(Role::VC);
    }

    /**
     * Determine whether the user can view the BMID.
     */
    public function show(Person $user, Bmid $bmid): bool
    {
        return false;
    }

    /**
     * Determine whether the user can export the BMIDs for printing
     */

    public function export(Person $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create assets.
     *
     */
    public function create(Person $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the BMID.
     *
     */
    public function update(Person $user, Bmid $bmid): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the BMID.
     *
     */
    public function delete(Person $user, Bmid $bmid): bool
    {
        return false;
    }

    /**
     * Determine whether the user can set special titles
     *
     */
    public function setBMIDTitles(Person $user): bool
    {
        return false;
    }

    public function syncAppreciations(Person $user): bool
    {
        return false;
    }
}
