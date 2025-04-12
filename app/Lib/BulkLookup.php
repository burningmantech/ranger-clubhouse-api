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
                $years = $person->years_combined;
                $results[] = [
                    'result' => 'success',
                    'id' => $person->id,
                    'callsign' => $person->callsign,
                    'first_name' => $person->first_name,
                    'preferred_name' => $person->preferred_name,
                    'last_name' => $person->last_name,
                    'status' => $person->status,
                    'email' => $person->email,
                    'last_worked' => !empty($years) ? last($years) : 0,
                    'vintage' => $person->vintage,
                    'years_worked' => count($years),
                    'street1' => $person->street1,
                    'street2' => $person->street2,
                    'apt' => $person->apt,
                    'city' => $person->city,
                    'state' => $person->state,
                    'zip' => $person->zip,
                    'country' => $person->country,
                    'home_phone' => $person->home_phone,
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
