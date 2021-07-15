<?php
namespace App\Lib;

use App\Models\Person;
use App\Models\PersonPosition;

class BulkPositionGrantRevoke
{
    /**
     * Bulk grant or revoke a single position for a list of callsigns
     *
     * @param string $callsigns callsign list separated by a newline
     * @param int $positionId Position ID to grant or revoke
     * @param bool $grant true=grant the position, false=revoke it
     * @param bool $commit true=commit the changes, false=verify the callsigns & position changes
     * @return array
     */

    public static function execute(string $callsigns, int $positionId, bool $grant, bool $commit) : array
    {
        $lines = explode("\n", $callsigns);
        $errors = 0;
        $results = [];

        $reason = $grant ? 'bulk position grant': 'bulk position revoke';

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

            $exists = PersonPosition::havePosition($person->id, $positionId);

            $result = [
                'id' => $person->id,
                'callsign'=> $person->callsign,
            ];

            if ($grant) {
                if ($exists) {
                    $result['errors'] = 'Position already granted';
                } else {
                    if ($commit) {
                        PersonPosition::addIdsToPerson($person->id, [$positionId], $reason);
                    }
                    $result['success'] = true;
                }
            } else {
                if (!$exists) {
                    $result['errors'] = 'Position already revoked';
                } else {
                    if ($commit) {
                        PersonPosition::removeIdsFromPerson($person->id, [$positionId], $reason);
                    }
                    $result['success'] = true;
                }
            }

            $results[] = $result;
        }

        return $results;
    }
}