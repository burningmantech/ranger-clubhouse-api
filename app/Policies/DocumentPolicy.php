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
     * @param Person $user
     * @return bool
     */

    public function index(Person $user): bool
    {
        return false;
    }

    /**
     * A normal user may not create access documents
     * @param Person $user
     * @return false
     */

    public function store(Person $user): bool
    {
        return false;
    }


    /**
     * Determine whether the user can view the document.
     * @param Person $user
     * @return true
     */

    public function show(Person $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the Document.
     * @param Person $user
     * @param Document $document
     * @return false
     */

    public function update(Person $user, Document $document) : bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the Document.
     * @param Person $user
     * @param Document $document
     * @return bool
     */

    public function destroy(Person $user, Document $document): bool
    {
        return false;
    }
}
