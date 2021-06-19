<?php

namespace App\Lib\Reports;

use App\Models\Person;

class PeopleByStatusReport
{
    /**
     * Report on all assigned account statuses
     *
     * @return array
     */

    public static function execute() : array
    {
        $statusGroups = Person::select('id', 'callsign', 'status')
            ->orderBy('status')
            ->orderBy('callsign')
            ->get()
            ->groupBy('status');

        return $statusGroups->sortKeys()->map(function ($group, $status) {
            return [
                'status' => $status,
                'people' => $group->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'callsign' => $row->callsign
                    ];
                })->values()->toArray()
            ];
        })->values()->toArray();
    }
}