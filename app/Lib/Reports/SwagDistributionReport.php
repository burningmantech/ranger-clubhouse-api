<?php

namespace App\Lib\Reports;

use App\Models\PersonSwag;

class SwagDistributionReport
{
    public static function execute(int $year): array
    {
        $swag = PersonSwag::findForQuery(['year_issued' => $year, 'include_person' => true]);
        $swagByPerson = $swag->groupBy('person_id');

        $people = [];
        foreach ($swagByPerson as $personId => $items) {
            $people[] = [
                'id' => $personId,
                'callsign' => $items[0]->person->callsign,
                'items' => $items->map(fn($item) => [
                    'id' => $item->swag->id,
                    'title' => $item->swag->title,
                    'type' => $item->swag->type,
                    'shirt_type' => $item->swag->shirt_type,
                ])->values()->toArray()
            ];
        }

        usort($people, fn ($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

        return $people;
    }
}