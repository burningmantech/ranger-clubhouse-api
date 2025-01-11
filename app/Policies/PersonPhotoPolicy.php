<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonPhoto;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonPhotoPolicy
{
    use HandlesAuthorization;

    public function before(Person $user): ?bool
    {
        if ($user->hasRole([Role::ADMIN, Role::VC])) {
            return true;
        }

        return null;
    }

    public function index(Person $user): false
    {
        return false;
    }

    public function reviewConfig(Person $user): false
    {
        return false;
    }

    public function upload(Person $user, Person $person): bool
    {
        return $user->id == $person->id;
    }

    /*
     * Determine whether the user can see the photo
     */

    public function photo(Person $user, Person $person): bool
    {
        return (
            $person->id == $user->id
            || $user->hasRole([Role::MANAGE, Role::MENTOR, Role::TRAINER])
            || $user->hasARTTrainerPositionRole()
        );
    }

    public function show(Person $user, PersonPhoto $personPhoto): false
    {
        return false;
    }

    public function store(Person $user): false
    {
        return false;
    }

    public function update(Person $user, PersonPhoto $personPhoto): false
    {
        return false;
    }

    public function destroy(Person $user, PersonPhoto $personPhoto): false
    {
        return false;
    }

    public function rejectPreview(Person $user, PersonPhoto $personPhoto): false
    {
        return false;
    }

    public function convertPhoto(Person $user): true
    {
        return true;
    }
}
