<?php

namespace App\Policies;

use App\Models\AssetPerson;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetPersonPolicy
{
    use HandlesAuthorization;

    public function before($user): ?true
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view the asset.
     */

    public function view(Person $user, AssetPerson $asset_person): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /**
     * Determine whether the user can create assets.
     */

    public function store(Person $user): false
    {
        return false;
    }

    /**
     * Determine whether the user can update the asset.
     *
     */

    public function update(Person $user, AssetPerson $asset_person): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /**
     * Determine whether the user can delete the asset.
     *
     */

    public function destroy(Person $user, AssetPerson $asset_person): false
    {
        return false;
    }

    /**
     * Can the user run the radio checkout report?
     */

    public function radioCheckoutReport(Person $user): false
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }
}
