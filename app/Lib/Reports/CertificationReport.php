<?php

namespace App\Lib\Reports;

use App\Models\Certification;
use App\Models\Person;
use App\Models\PersonCertification;

class CertificationReport
{
    /**
     * Report on hours and credits for the given year
     *
     * @param array $certificationIds
     * @return array
     */

    public static function execute(array $certificationIds): array
    {
        $certifications = Certification::whereIn('id', $certificationIds)
            ->orderBy('title')
            ->get();

        $personCertifications = PersonCertification::select('person_certification.*')
            ->join('person', 'person.id', 'person_certification.person_id')
            ->whereIn('person.status', Person::ACTIVE_STATUSES)
            ->whereIn('certification_id', $certificationIds)
            ->with('person:id,callsign,status,first_name,last_name')
            ->get()
            ->groupBy('person_id');

        $people = [];
        foreach ($personCertifications as $personId => $rows) {
            $certs = [];
            foreach ($certifications as $c) {
                $pc = $rows->firstWhere('certification_id', $c->id);
                if ($pc) {
                    $certs[] = [
                        'id' => $c->id,
                        'held' => true,
                        'issued_on' => $pc->issued_on?->toDateString(),
                        'trained_on' =>  $pc->trained_on?->toDateString(),
                        'card_number' => $pc->card_number,
                        'notes' => $pc->notes,
                    ];
                } else {
                    $certs[] = ['id' => $c->id];
                }
            }

            $person = $rows[0]->person;
            $people[] = [
                'id' => $personId,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'certifications' => $certs,
            ];
        }

        usort($people, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
        return [
            'certifications' => $certifications,
            'people' => $people
        ];
    }
}