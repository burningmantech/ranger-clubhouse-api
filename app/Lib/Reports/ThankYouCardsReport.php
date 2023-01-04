<?php

namespace App\Lib\Reports;

use App\Models\Person;

class ThankYouCardsReport
{
    /**
     * Figure out who to send Thank You cards to.
     *
     * @param int $year
     * @return array
     */
    public static function execute(int $year): array
    {
        $people = Person::whereNotIn('status',
                    [
                        Person::ALPHA, Person::AUDITOR, Person::BONKED,
                        Person::PAST_PROSPECTIVE, Person::PROSPECTIVE,
                        Person::SUSPENDED, Person::UBERBONKED
                    ])
            ->whereRaw('EXISTS (SELECT 1 FROM timesheet WHERE timesheet.person_id=person.id AND YEAR(on_duty)=? LIMIT 1)', [$year])
            ->orderBy('callsign')
            ->get();

        return $people->map(function ($row) {
            return [
                'id' => $row->id,
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
                'callsign' => $row->callsign,
                'status' => $row->status,
                'email' => $row->email,
                'bpguid' => $row->bpguid,
                'street1' => $row->street1,
                'street2' => $row->street2,
                'apt' => $row->apt,
                'city' => $row->city,
                'state' => $row->state,
                'zip' => $row->zip,
                'country' => $row->country,
            ];
        })->values()->toArray();
    }
}