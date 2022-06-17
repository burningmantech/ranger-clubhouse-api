<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\AccessDocument;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccessDocumentPolicy
{
    use HandlesAuthorization;

    public function before(Person $user)
    {
        if ($user->hasRole([Role::ADMIN, Role::EDIT_ACCESS_DOCS])) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the AccessDocument.
     */
    public function index(Person $user, $personId)
    {
        return ($user->id == $personId);
    }

    /**
     * Determine whether the user can see the current access document summary.
     */
    public function current(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can see the expiring tickets.
     */
    public function expiring(Person $user)
    {
        return false;
    }


    /**
     * A normal user may not create access doucments
     */
    public function create(Person $user)
    {
        return false;
    }

    /**
     * Is the person allowed to bump the expiration dates in mass?
     * @param Person $user
     * @return bool
     */
    public function bumpExpiration(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can view the AccessDocument.
     *
     */
    public function view(Person $user, AccessDocument $accessDocument)
    {
        return ($user->id == $accessDocument->person_id);
    }

    /**
     * Determine whether the user can update the AccessDocument.
     *
     */
    public function update(Person $user, AccessDocument $accessDocument)
    {
        return ($user->id == $accessDocument->person_id);
    }

    /**
     * Determine whether the user can delete the AccessDocument.
     *
     */
    public function destroy(Person $user, AccessDocument $accessDocument)
    {
        return ($user->id == $accessDocument->person_id);
    }

    public function storeSOSWAP(Person $user, $personId)
    {
        return ($user->id == $personId);
    }

    public function bulkComment(Person $user)
    {
        return false;
    }

    public function markSubmitted(Person $user)
    {
        return false;
    }

    public function grantWAPs(Person $user)
    {
        return false;
    }

    public function grantAlphaWAPs(Person $user)
    {
        return false;
    }

    public function grantVehiclePasses(Person $user)
    {
        return false;
    }

    public function setStaffCredentialsAccessDate(Person $user)
    {
        return false;
    }

    public function cleanAccessDocsFromPriorEvent(Person $user)
    {
        return false;
    }

    public function bankAccessDocuments(Person $user)
    {
        return false;
    }

    public function expireAccessDocuments(Person $user)
    {
        return false;
    }

    public function delivery(Person $user, $personId)
    {
        return ($user->id == $personId);
    }

    public function unbankAccessDocuments(Person $user)
    {
        return false;
    }
}
