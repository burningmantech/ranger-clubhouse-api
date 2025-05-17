<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonBanner;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonBannerPolicy
{
    use HandlesAuthorization;

    public function before($user): ?bool
    {
        if ($user->hasRole([Role::ADMIN, Role::VC])) {
            return true;
        }

        return null;
    }

    public function index(Person $user): bool
    {
        return false;
    }

    public function show(Person $user, PersonBanner $person_banner): bool
    {
        return false;
    }

    public function indexForPerson(Person $user): bool
    {
        return $user->hasRole([
            Role::EVENT_MANAGEMENT,
            Role::MENTOR,
            Role::TRAINER,
        ]);
    }

    public function store(Person $user, PersonBanner $person_banner): bool
    {
        return false;
    }

    public function update(Person $user, PersonBanner $person_banner): bool
    {
        return false;
    }

    public function destroy(Person $user, PersonBanner $person_banner): bool
    {
        return false;
    }
}
