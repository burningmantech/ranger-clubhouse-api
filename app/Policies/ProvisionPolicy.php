<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Provision;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProvisionPolicy
{
    use HandlesAuthorization;

    public function before(Person $user)
    {
        if ($user->hasRole([Role::ADMIN, Role::EDIT_ACCESS_DOCS])) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the provision.
     */

    public function index(Person $user, $personId): bool
    {
        return ($user->id == $personId);
    }


    /**
     * A normal user may not create provisions
     *
     * @param Person $user
     * @return bool
     */

    public function create(Person $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view a provision.
     *
     * @param Person $user
     * @param Provision $provision
     * @return bool
     */

    public function view(Person $user, Provision $provision): bool
    {
        return ($user->id == $provision->person_id);
    }

    /**
     * Determine whether the user can update the Provision.
     *
     * @param Person $user
     * @param Provision $provision
     * @return bool
     */

    public function update(Person $user, Provision $provision): bool
    {
        return ($user->id == $provision->person_id);
    }

    /**
     * Determine whether the user can delete the Provision.
     *
     * @param Person $user
     * @param Provision $provision
     * @return bool
     */

    public function destroy(Person $user, Provision $provision): bool
    {
        return ($user->id == $provision->person_id);
    }

    /**
     * Can the user add comments in bulk?
     *
     * @param Person $user
     * @return false
     */

    public function bulkComment(Person $user)
    {
        return false;
    }

    /**
     * Can the user clean the provisions from the prior event?
     *
     * @param Person $user
     * @return false
     */

    public function cleanProvisionsFromPriorEvent(Person $user): bool
    {
        return false;
    }

    /**
     * Can the user bank all the unused provisions?
     *
     * @param Person $user
     * @return false
     */

    public function bankProvisions(Person $user): bool
    {
        return false;
    }

    /**
     * Can the user un-bank all the banked provisions?
     *
     * @param Person $user
     * @return false
     */

    public function unbankProvisions(Person $user): bool
    {
        return false;
    }

    /**
     * Can the user expire the provisions?
     *
     * @param Person $user
     * @return bool
     */

    public function expireProvisions(Person $user): bool
    {
        return false;
    }

    /**
     * Can the user run the unsubmit provisions recommendation report?
     *
     * @param Person $user
     * @return bool
     */

    public function unsubmitRecommendations(Person $user): bool
    {
        return false;
    }

    /**
     * Can the user bulk un-submit provisions?
     *
     * @param Person $user
     * @return bool
     */

    public function unsubmitProvisions(Person $user): bool
    {
        return false;
    }

    /**
     * Can the user update the statuses for earned provisions?
     *
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function statuses(Person $user, Person $person) : bool {
        return $user->id == $person->id;
    }
}
