<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonTeam;
use App\Models\Position;

class BulkTeamGrantRevoke
{
    /**
     * Bulk grant or revoke a single position for a list of callsigns
     *
     * @param string $callsigns callsign list separated by a newline
     * @param int $teamId Team ID to grant or revoke
     * @param bool $grant true=grant the position, false=revoke it
     * @param bool $commit true=commit the changes, false=verify the callsigns & position changes
     * @return array
     */

    public static function execute(string $callsigns, int $teamId, bool $grant, bool $commit): array
    {
        $lines = explode("\n", $callsigns);
        $results = [];

        $reason = $grant ? 'bulk team grant' : 'bulk team revoke';

        if ($commit) {
            $positionIds = Position::where('team_id', $teamId)
                ->whereIn('team_category', $grant ? [Position::TEAM_CATEGORY_ALL_MEMBERS] : [Position::TEAM_CATEGORY_ALL_MEMBERS , Position::TEAM_CATEGORY_OPTIONAL])
                ->pluck('id')
                ->toArray();
        }

        foreach ($lines as $callsign) {
            $normalized = Person::normalizeCallsign($callsign);
            if (empty($normalized)) {
                continue;
            }

            $person = Person::where('callsign_normalized', $normalized)->first();

            if (!$person) {
                $results[] = [
                    'callsign' => $callsign,
                    'errors' => 'Callsign not found'
                ];
                continue;
            }

            $result = [
                'id' => $person->id,
                'callsign' => $person->callsign,
            ];

            if (in_array($person->status, [...Person::LOCKED_STATUSES, Person::PAST_PROSPECTIVE])) {
                $result['errors'] = "Has status [{$person->status}], team cannot be granted thru this interface";
                $results[] = $result;
                continue;
            }

            $exists = PersonTeam::haveTeam($person->id, $teamId);

            if ($grant) {
                if ($exists) {
                    $result['errors'] = 'Team already granted';
                } else {
                    if ($commit) {
                        PersonTeam::addPerson($teamId, $person->id, $reason);
                        if (!empty($positionIds)) {
                            PersonPosition::addIdsToPerson($person->id, $positionIds, 'bulk team grant');
                        }
                    }
                    $result['success'] = true;
                }
            } else {
                if (!$exists) {
                    $result['errors'] = 'Team already revoked';
                } else {
                    if ($commit) {
                        PersonTeam::removePerson($teamId, $person->id, $reason);
                        if (!empty($positionIds)) {
                            PersonPosition::removeIdsFromPerson($person->id, $positionIds, 'bulk team grant');
                        }
                    }
                    $result['success'] = true;
                }
            }

            $results[] = $result;
        }

        return $results;
    }

}