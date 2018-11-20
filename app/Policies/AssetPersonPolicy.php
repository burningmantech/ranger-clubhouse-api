<?php

namespace App\Policies;

use App\Models\AssetPerson;
use App\Models\Person;
use App\Models\Role;

use Illuminate\Auth\Access\HandlesAuthorization;

class AssetPersonPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole([ Role::ADMIN, Role::MANAGE ])) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the asset.
     */
    public function view(Person $user, AssetPerson $asset_person)
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
    public function update(Person $user, AssetPerson $asset_person)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the asset.
     *
     */
    public function destroy(Person $user, AssetPerson $asset_person)
    {
        return false;
    }

    /**
     * Can the user checkout the asset?
     */

     public function checkout(Person $user) {
         return false;
     }

     /**
      * Can the user checkin the asset?
      */

      public function checkin(Person $user, AssetPerson $asset_person) {
          return false;
      }

}
