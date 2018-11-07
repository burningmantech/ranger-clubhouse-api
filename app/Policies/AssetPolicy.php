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
     * Determine whether the user can view the asset.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\App\Models\Asset  $asset
     * @return mixed
     */
    public function view(Person $user, Asset $asset)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine whether the user can create assets.
     *
     * @param  \App\Models\Person  $user
     * @return mixed
     */
    public function store(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the asset.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\App\Models\Asset  $asset
     * @return mixed
     */
    public function update(Person $user, Asset $asset)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the asset.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\App\Models\Asset  $asset
     * @return mixed
     */
    public function delete(Person $user, Asset $asset)
    {
        return false;
    }
}
