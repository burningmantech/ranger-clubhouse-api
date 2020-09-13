<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Document;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentPolicy
{
    use HandlesAuthorization;

    public function before(Person $user)
    {
        if ($user->hasRole([Role::ADMIN, Role::MEGAPHONE])) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the Document.
     */
    public function index(Person $user)
    {
        return false;
    }

    /**
     * A normal user may not create access doucments
     */
    public function store(Person $user)
    {
        return false;
    }


    /**
     * Determine whether the user can view the Document.
     *
     */
    public function show(Person $user)
    {
        return true;
    }

    /**
     * Determine whether the user can update the Document.
     *
     */
    public function update(Person $user, Document $document)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the Document.
     *
     */
    public function destroy(Person $user, Document $document)
    {
        return false;
    }
}
