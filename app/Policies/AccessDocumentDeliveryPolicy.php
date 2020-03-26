<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\AccessDocumentDelivery;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccessDocumentDeliveryPolicy
{
    use HandlesAuthorization;

    public function before(Person $user) {
        if ($user->hasRole([Role::ADMIN, Role::EDIT_ACCESS_DOCS])) {
            return true;
        }
    }
    /*
     * Determine whether the user can view the AccessDocumentDelivery.
     */
    public function index(Person $user, $personId)
    {
        return ($user->id == $personId);
    }

    /*
     * Person may create a delivery record
     */
    public function create(Person $user)
    {
        return true;
    }

    /*
     * Determine whether the user can view the AccessDocumentDelivery.
     *
     */
    public function show(Person $user, AccessDocumentDelivery $accessDocument)
    {
        return ($user->id == $accessDocument->person_id);
    }

    /*
     * Determine whether the user can update the AccessDocumentDelivery.
     */
    public function update(Person $user, AccessDocumentDelivery $accessDocument)
    {
        return ($user->id == $accessDocument->person_id);
    }

    /**
     * Determine whether the user can delete the AccessDocumentDelivery.
     *
     */
    public function delete(Person $user, AccessDocumentDelivery $accessDocument)
    {
        return ($user->id == $accessDocument->person_id);
    }

    /**
     * Determine if a user can create and/or update an ADD
     */

     public function delivery(Person $user, $personId)
     {
         return ($user->id == $personId);
     }
}
