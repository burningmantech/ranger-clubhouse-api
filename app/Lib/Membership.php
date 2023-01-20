<?php

namespace App\Lib;

use App\Models\PersonPosition;
use App\Models\PersonRole;
use App\Models\PersonTeam;
use App\Models\Position;
use App\Models\PositionRole;
use App\Models\Team;
use App\Models\TeamRole;

class Membership
{
    /**
     * Retrieve the assigned team and positions for a given person
     *
     * @param int $personId
     * @return array
     */

    public static function retrieveForPerson(int $personId): array
    {
        $teams = PersonTeam::findAllTeamsForPerson($personId);
        $teamsById = $teams->keyBy('id');
        $positions = PersonPosition::findForPerson($personId);

        $notMember = [];
        foreach ($positions as $position) {
            if ($position->team_id && !$teamsById->has($position->team_id)) {
                $notMember[$position->team_id] = true;
            }
        }

        return [
            'teams' => $teams,
            'not_member_teams' => empty($notMember) ? [] : Team::find(array_keys($notMember))->sortBy('title')->values(),
            'positions' => $positions,
        ];
    }

    /**
     * Grant and/or revoke positions for a person.
     *
     * - For general positions (not associated with a team, usually basic dirt) the user must
     *   hold the VC or Mentor roles
     * - For team positions, the user must be a team administrator for the team the position belongs to.
     *
     * @param int $userId
     * @param int $personId
     * @param array $positionIds
     * @param $reason
     * @param $isAdmin
     */

    public static function updatePositionsForPerson(int $userId, int $personId, array $positionIds, $reason, $isAdmin): void
    {
        $positions = PersonPosition::findForPerson($personId);

        list ($newIds, $deleteIds) = self::determineGrantRevokes($positions, $positionIds);

        if (!$isAdmin) {
            // Need to check on each position to see if it's allowed
            $manageIds = PersonTeam::retrieveTeamIdsForManager($userId);
            $newIds = self::canManagePositions($newIds, $manageIds);
            $deleteIds = self::canManagePositions($deleteIds, $manageIds);
        }

        // Mass revoke the old positions
        if (!empty($deleteIds)) {
            PersonPosition::removeIdsFromPerson($personId, $deleteIds, $reason);
        }

        // Mass grant new positions
        if (!empty($newIds)) {
            PersonPosition::addIdsToPerson($personId, $newIds, $reason);
        }

        PersonRole::clearCache($personId);
    }

    /**
     * Check to see if the user can grant or revoke various positions for a person.
     *
     * @param $ids
     * @param $manageIds
     * @return array
     */

    public static function canManagePositions($ids, $manageIds): array
    {
        if (empty($ids)) {
            return [];
        }

        $positions = Position::find($ids);
        $clearedIds = [];

        foreach ($positions as $position) {
            if ($position->team_id && in_array($position->team_id, $manageIds)) {
                $clearedIds[] = $position->id;
            }
        }

        return $clearedIds;
    }

    /**
     * Determine which ids are to be added and deleted based off what ids are currently granted.
     *
     * @param $groups
     * @param $groupIds
     * @return array[]
     */

    public static function determineGrantRevokes($groups, $groupIds): array
    {
        $newIds = [];
        $deleteIds = [];
        $existingIds = [];

        // Find the new ids
        foreach ($groupIds as $id) {
            $found = false;
            foreach ($groups as $group) {
                if ($group->id == $id) {
                    $found = true;
                    $existingIds[] = $id;
                    break;
                }
            }

            if (!$found) {
                $newIds[] = $id;
            }
        }

        // Find the ids to be deleted
        foreach ($groups as $group) {
            if (!in_array($group->id, $groupIds)) {
                $deleteIds[] = $group->id;
            }
        }

        return [$newIds, $deleteIds, $existingIds];
    }

    /**
     * Grant and/or revoke teams for a person.
     *
     * @param $userId
     * @param $personId
     * @param $teamIds
     * @param $managerIds
     * @param $reason
     * @param $isAdmin
     */

    public static function updateTeamsForPerson($userId, $personId, $teamIds, $managerIds, $reason, $isAdmin): void
    {
        $memberships = PersonTeam::findAllTeamsForPerson($personId);

        list ($newIds, $deleteIds, $existingIds) = self::determineGrantRevokes($memberships, $teamIds);

        if (!$isAdmin) {
            // Need to check on each position to see if it's allowed
            $manageIds = PersonTeam::retrieveTeamIdsForManager($userId);

            $newIds = self::canManageTeam($newIds, $manageIds);
            $deleteIds = self::canManageTeam($deleteIds, $manageIds);
            $existingIds = self::canManageTeam($existingIds, $manageIds);
        }

        foreach ($deleteIds as $id) {
            PersonTeam::removePerson($id, $personId, $reason);
        }

        foreach ($newIds as $id) {
            PersonTeam::addPerson($id, $personId, in_array($id, $managerIds), $reason);
        }

        foreach ($existingIds as $id) {
            $pt = PersonTeam::findForPerson($id, $personId);
            if (!$pt) {
                continue;
            }

            if ($isAdmin) {
                $pt->is_manager = in_array($id, $managerIds);
            }
            $pt->auditReason = $reason;
            $pt->saveWithoutValidation();
        }

        PersonRole::clearCache($personId);
    }

    /**
     * Can the person manage teams?
     *
     * @param $ids
     * @param $manageIds
     * @return array
     */

    public static function canManageTeam($ids, $manageIds): array
    {
        return array_filter($ids, fn($id) => in_array($id, $manageIds));
    }

    /**
     * Grant and/or revoke teams for a person.
     *
     * @param $positionId
     * @param $roleIds
     * @param $reason
     */

    public static function updatePositionRoles($positionId, $roleIds, $reason): void
    {
        $existingRoles = PositionRole::findAllForPosition($positionId);
        $existingRolesById = $existingRoles->keyBy('role_id');

        // Find the ids to add
        foreach ($roleIds as $id) {
            if (!$existingRolesById->has($id)) {
                PositionRole::add($positionId, $id, $reason);
            }
        }
        // Find the ids to delete
        foreach ($existingRoles as $existing) {
            if (!in_array($existing->role_id, $roleIds)) {
                PositionRole::remove($positionId, $existing->role_id, $reason);
            }
        }
    }

    /**
     * Grant and/or revoke roles for a team.
     *
     * @param $teamId
     * @param $roleIds
     * @param $reason
     */

    public static function updateTeamRoles($teamId, $roleIds, $reason): void
    {
        $existingRoles = TeamRole::findAllForTeam($teamId);
        $existingRolesById = $existingRoles->keyBy('role_id');

        // Find the ids to add
        foreach ($roleIds as $id) {
            if (!$existingRolesById->has($id)) {
                TeamRole::add($teamId, $id, $reason);
            }
        }
        // Find the ids to delete
        foreach ($existingRoles as $existing) {
            if (!in_array($existing->role_id, $roleIds)) {
                TeamRole::remove($teamId, $existing->role_id, $reason);
            }
        }
    }

}