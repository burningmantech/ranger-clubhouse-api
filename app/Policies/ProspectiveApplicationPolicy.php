<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\ProspectiveApplication;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProspectiveApplicationPolicy
{
    use HandlesAuthorization;

    public function before($user): ?true
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

    public function search(Person $user): false
    {
        return false;
    }

    /**
     * Determine whether the user can create a prospective application
     */

    public function store(Person $user): false
    {
        return false;
    }

    /**
     * Can the person see prospective application
     *
     * @param Person $user
     * @param ProspectiveApplication $prospectiveApplication
     * @return false
     */

    public function show(Person $user, ProspectiveApplication $prospectiveApplication): false
    {
        return false;
    }

    /**
     * Determine whether the user can update a prospective application
     */

    public function update(Person $user, ProspectiveApplication $prospectiveApplication): false
    {
        return false;
    }

    /**
     * Determine whether the user can delete a prospective application
     */

    public function destroy(Person $user, ProspectiveApplication $prospectiveApplication): false
    {
        return false;
    }

    /**
     * Determine whether the user can update the application's status, and maybe send an email.
     */

    public function updateStatus(Person $user, ProspectiveApplication $prospectiveApplication): false
    {
        return false;
    }

    /**
     * Determine whether if the user can add a note
     */

    public function addNote(Person $user, ProspectiveApplication $prospectiveApplication): false
    {
        return false;
    }

    /**
     * Determine whether if the user can update a note
     */

    public function updateNote(Person $user, ProspectiveApplication $prospectiveApplication): false
    {
        return false;
    }

    /**
     * Determine whether if the user can delete a note
     */

    public function deleteNote(Person $user, ProspectiveApplication $prospectiveApplication): false
    {
        return false;
    }

    /**
     * Can the user preview emails to be sent?
     *
     * @param Person $user
     * @param ProspectiveApplication $prospectiveApplication
     * @return false
     */

    public function previewEmail(Person $user, ProspectiveApplication $prospectiveApplication): false
    {
        return false;
    }

    /**
     * Can the user email the applicant?
     *
     * @param Person $user
     * @param ProspectiveApplication $prospectiveApplication
     * @return false
     */

    public function sendEmail(Person $user, ProspectiveApplication $prospectiveApplication): false
    {
        return false;
    }

    /**
     * Can the user see the email logs?
     *
     * @param Person $user
     * @param ProspectiveApplication $prospectiveApplication
     * @return false
     */

    public function emailLogs(Person $user, ProspectiveApplication $prospectiveApplication): false
    {
        return false;
    }

    /**
     * Can the user import applications?
     *
     * @param Person $user
     * @return false
     */

    public function import(Person $user): false
    {
        return false;
    }

    /**
     * Can the person create prospective accounts from approved applications.
     *
     * @param Person $user
     * @return false
     */

    public function createProspectives(Person $user): false
    {
        return false;
    }

    /**
     * Can the person extract handles using an AI Engine?
     *
     */

    public function handlesExtract(Person $user) : false
    {
        return false;
    }
}
