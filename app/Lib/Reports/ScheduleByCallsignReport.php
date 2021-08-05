<?php

namespace App\Lib\Reports;

use Illuminate\Support\Facades\DB;

class ScheduleByCallsignReport
{
    /*
     * Schedule By Callsign report
     */

    public static function execute(int $year)
    {
        $peopleGroups = DB::table('slot')
            ->select('person_slot.*',
                'person.callsign',
                'person.status',
                'slot.id as slot_id',
                'slot.begins',
                'slot.ends',
                'slot.description',
                'slot.position_id',
                'position.title as position_title',
                'position.active as position_active'
            )
            ->join('person_slot', 'slot.id', 'person_slot.slot_id')
            ->join('position', 'position.id', 'slot.position_id')
            ->join('person', 'person.id', 'person_slot.person_id')
            ->whereYear('slot.begins', $year)
            ->orderBy('callsign')
            ->orderBy('slot.begins')
            ->get()
            ->groupBy('person_id');

        $positions = [];
        $slots = [];

        $people = $peopleGroups->map(function ($signups) use (&$positions, &$slots) {
            $person = $signups[0];
            return [
                'id' => $person->person_id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'slot_ids' => $signups->map(function ($row) use (&$positions, &$slots) {
                    $positions[$row->position_id] ??= [
                        'title' => $row->position_title,
                        'active' => $row->position_active,
                    ];

                    $slots[$row->slot_id] ??= [
                        'id' => $row->slot_id,
                        'position_id' => $row->position_id,
                        'begins' => (string)$row->begins,
                        'ends' => (string)$row->ends,
                        'description' => $row->description,
                    ];

                    return $row->slot_id;
                })->values()
            ];
        })->values();

        return [
            'people' => $people,
            'positions' => $positions,
            'slots' => $slots,
        ];
    }
}