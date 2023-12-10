<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\OauthClient;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class OauthClientPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::TECH_NINJA)) {
            return true;
        }
    }

    /**
     * Determine if the user can view all the records
     */

    public function index(Person $user): false
    {
        return false;
    }

    /**
     * Determine if the user can see one record
     */

    public function show(Person $user): false
    {
        return false;
    }

    /**
     * Determine whether the user can create a OAuth Client document.
     */
    public function store(Person $user): false
    {
        return false;
    }

    /**
     * Determine whether the user can update the OAuth Client.
     */

    public function update(Person $user, OauthClient $client): false
    {
        return false;
    }

    /**
     * Determine whether the user can delete the OAuth Client.
     */
    public function destroy(Person $user, OauthClient $client): false
    {
        return false;
    }
}
