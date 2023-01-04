<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\Timesheet;

class BulkLookup
{
    /**
     * Lookup an array of callsigns and/or emails.
     *
     * @param array $people
     * @return array
     */

    public static function retrieveByCallsignOrEmail(array $people) : array
    {
        $results = [];
        foreach ($people as $name) {
            if (str_contains($name, '@')) {
                $person = Person::where('email', $name)->first();
            } else {
                $normalized = Person::normalizeCallsign($name);
                if (empty($normalized)) {
                    continue;
                }

                $person = Person::where('callsign_normalized', $normalized)->first();
            }

            if ($person) {
                $years = Timesheet::findYears($person->id, Timesheet::YEARS_WORKED);
                $results[] = [
                    'result' => 'success',
                    'id' => $person->id,
                    'callsign' => $person->callsign,
                    'first_name' => $person->first_name,
                    'last_name' => $person->last_name,
                    'status' => $person->status,
                    'email' => $person->email,
                    'last_worked' => last($years),
                    'vintage' => $person->vintage,
                    'years_worked' => count($years),
                ];
            } else {
                $results[] = [
                    'result' => 'not-found',
                    'person' => $name
                ];
            }
        }

        return $results;
    }
}
