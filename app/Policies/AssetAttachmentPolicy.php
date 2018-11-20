<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\AssetAttachment;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetAttachmentPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the asset attachment.
     */
    public function show(Person $user, Asset $asset_attachment)
    {
        return false;
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
    public function update(Person $user, AssetAttachment $asset_attachment)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the asset.
     *
     */
    public function delete(Person $user, AssetAttachment $asset_attachment)
    {
        return false;
    }

}
