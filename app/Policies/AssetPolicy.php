<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Asset;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user see all the assets
     */
    public function index(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine whether the user can view the asset.
     */
    public function view(Person $user, Asset $asset)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine whether the user can create assets.
     *
     */
    public function store(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the asset.
     *
     */
    public function update(Person $user, Asset $asset)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the asset.
     *
     */
    public function delete(Person $user, Asset $asset)
    {
        return false;
    }

    /*
     * Determine whether the user can checkout assets
     */

    public function checkout(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /*
     * Determine whether the user can checkin assets
     */

    public function checkin(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

}
