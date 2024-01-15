<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonTeam;

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
        $errors = 0;
        $results = [];

        $reason = $grant ? 'bulk team grant' : 'bulk team revoke';

        foreach ($lines as $callsign) {
            $normalized = Person::normalizeCallsign($callsign);
            if (empty($normalized)) {
                continue;
            }

            $person = Person::where('callsign_normalized', $normalized)->first();

            if (!$person) {
                $errors++;
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
                $errors++;
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
                    }
                    $result['success'] = true;
                }
            } else {
                if (!$exists) {
                    $result['errors'] = 'Team already revoked';
                } else {
                    if ($commit) {
                        PersonTeam::removePerson($teamId, $person->id, $reason);
                    }
                    $result['success'] = true;
                }
            }

            $results[] = $result;
        }

        return $results;
    }

}