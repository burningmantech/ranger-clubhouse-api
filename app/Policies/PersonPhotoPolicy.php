<?php

namespace App\Policies;

use App\Models\PersonPhoto;
use App\Models\Person;
use App\Models\Role;

use Illuminate\Auth\Access\HandlesAuthorization;

class PersonPhotoPolicy
{
    use HandlesAuthorization;

    public function before(Person $user) {
        if ($user->hasRole([ Role::ADMIN, Role::VC ])) {
            return true;
        }
    }

    public function index(Person $user)
    {
        return false;
    }

    public function reviewConfig(Person $user)
    {
        return false;
    }

    public function upload(Person $user, Person $person)
    {
        return $user->id == $person->id;
    }

    /*
     * Determine whether the user can see the photo
     */

    public function photo(Person $user, Person $person)
    {
        return (
            $person->id == $user->id ||
            $user->hasRole([ Role::MANAGE, Role::MENTOR, Role::TRAINER, Role::ART_TRAINER ])
        );
    }

    public function show(Person $user, PersonPhoto $personPhoto)
    {
        return false;
    }

    public function store(Person $user)
    {
        return false;
    }

    public function update(Person $user, PersonPhoto $personPhoto)
    {
        return false;
    }

    public function destroy(Person $user, PersonPhoto $personPhoto)
    {
        return false;
    }

    public function review(Person $user, PersonPhoto $personPhoto) {
        return false;
    }
}
