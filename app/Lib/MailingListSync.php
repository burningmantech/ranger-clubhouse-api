<?php

namespace App\Lib;

use App\Jobs\MailingListSyncJob;
use App\Models\Person;
use App\Models\PersonTeam;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;

/**
 * Figure out which Google Groups memberships need to change when a person's email address
 * or cadre/delegation memberships change, and queue up the job to apply them.
 */

class MailingListSync
{
    const array SYNCED_TEAM_TYPES = [Team::TYPE_CADRE, Team::TYPE_DELEGATION];

    public static function isEnabled(): bool
    {
        return (bool)setting('GoogleGroupsSyncEnabled');
    }

    /**
     * A person changed their email address - swap the old address for the new one in Allcom, Announce,
     * and any cadre or delegation lists the person is a member of.
     *
     * @param Person $person
     * @param string|null $oldEmail
     * @return void
     */

    public static function emailChanged(Person $person, ?string $oldEmail): void
    {
        if (!self::isEnabled() || !in_array($person->status, Person::ACTIVE_STATUSES) || empty($person->email)) {
            return;
        }

        $groups = [GoogleGroups::ALLCOM, GoogleGroups::ANNOUNCE];
        $teamIds = PersonTeam::retrieveCadreMembershipIds($person->id);
        if (!empty($teamIds)) {
            foreach (Team::whereIn('id', $teamIds)->orderBy('title')->get() as $team) {
                if (GoogleGroups::isManagedGroup($team->email)) {
                    $groups[] = strtolower(trim($team->email));
                }
            }
        }

        $operations = [];
        foreach (array_unique($groups) as $group) {
            if (!empty($oldEmail) && strcasecmp($oldEmail, $person->email)) {
                $operations[] = ['action' => MailingListSyncJob::REMOVE, 'group' => $group, 'email' => $oldEmail];
            }
            $operations[] = ['action' => MailingListSyncJob::ADD, 'group' => $group, 'email' => $person->email];
        }

        self::dispatch($person, "email changed from {$oldEmail} to {$person->email}", $operations);
    }

    /**
     * A person was added to or removed from a team. Update the team's list if it is a cadre or delegation
     * with a Ranger Google Group as its email address.
     *
     * @param int $teamId
     * @param int $personId
     * @param string $action MailingListSyncJob::ADD or MailingListSyncJob::REMOVE
     * @return void
     */

    public static function teamMembershipChanged(int $teamId, int $personId, string $action): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $team = Team::find($teamId);
        if (!$team
            || !$team->active
            || !in_array($team->type, self::SYNCED_TEAM_TYPES)
            || !GoogleGroups::isManagedGroup($team->email)) {
            return;
        }

        $person = Person::find($personId);
        if (!$person || empty($person->email)) {
            return;
        }

        $reason = ($action == MailingListSyncJob::ADD ? 'added to ' : 'removed from ') . $team->title;
        self::dispatch($person, $reason, [
            ['action' => $action, 'group' => strtolower(trim($team->email)), 'email' => $person->email]
        ]);
    }

    private static function dispatch(Person $person, string $reason, array $operations): void
    {
        MailingListSyncJob::dispatch($person, $reason, $operations, Auth::id())->afterCommit();
    }
}
