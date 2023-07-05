<?php

namespace App\Policies;

use App\Models\AccessDocument;
use App\Models\Person;
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
    public function index(Person $user, $personId): bool
    {
        return ($user->id == $personId);
    }

    /**
     * Determine whether the user can see the current access document summary.
     */

    public function current(Person $user): false
    {
        return false;
    }

    /**
     * Determine whether the user can see the expiring tickets.
     */
    public function expiring(Person $user): false
    {
        return false;
    }


    /**
     * A normal user may not create access doucments
     */
    public function create(Person $user): false
    {
        return false;
    }

    /**
     * Is the person allowed to bump the expiration dates in mass?
     * @param Person $user
     * @return false
     */

    public function bumpExpiration(Person $user): false
    {
        return false;
    }

    /**
     * Determine whether the user can view the AccessDocument.
     *
     */
    public function view(Person $user, AccessDocument $accessDocument): bool
    {
        return ($user->id == $accessDocument->person_id);
    }

    /**
     * Determine whether the user can update the AccessDocument.
     *
     */
    public function update(Person $user, AccessDocument $accessDocument): bool
    {
        return ($user->id == $accessDocument->person_id);
    }

    /**
     * Determine whether the user can delete the AccessDocument.
     *
     */
    public function destroy(Person $user, AccessDocument $accessDocument): bool
    {
        return ($user->id == $accessDocument->person_id);
    }

    public function storeSOSWAP(Person $user, $personId): bool
    {
        return ($user->id == $personId);
    }

    public function changes(Person $user, AccessDocument $accessDocument) : bool
    {
        return false;
    }

    public function bulkComment(Person $user): false
    {
        return false;
    }

    public function markSubmitted(Person $user): false
    {
        return false;
    }

    public function grantWAPs(Person $user): false
    {
        return false;
    }

    public function grantAlphaWAPs(Person $user): false
    {
        return false;
    }

    public function grantVehiclePasses(Person $user): false
    {
        return false;
    }

    public function setStaffCredentialsAccessDate(Person $user): false
    {
        return false;
    }

    public function cleanAccessDocsFromPriorEvent(Person $user): false
    {
        return false;
    }

    public function bankAccessDocuments(Person $user): false
    {
        return false;
    }

    public function expireAccessDocuments(Person $user): false
    {
        return false;
    }

    public function delivery(Person $user, $personId): bool
    {
        return ($user->id == $personId);
    }

    public function unbankAccessDocuments(Person $user): false
    {
        return false;
    }

    public function statistics(Person $user)
    {
        return false;
    }

    public function wapCandidates(Person $user)
    {
        return false;
    }

    public function unclaimedTicketsWithSignups(Person $user)
    {
        return false;
    }

    public function claimedTicketsWithNoSignups(Person $user)
    {
        return false;
    }

    /**
     * Can the user see a person's ticketing progress?
     *
     * @param Person $user
     * @return bool
     */

    public function progress(Person $user): bool
    {
        return false;
    }

    /**
     * Can the user update a person's ticketing progress?
     *
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function updateProgress(Person $user, Person $person): bool
    {
        return $user->id == $person->id;
    }

    /**
     * Can the person run the special tickets report.
     *
     * @param Person $user
     * @return false
     */

    public function specialTicketsReport(Person $user) : false
    {
        return false;
    }
}
