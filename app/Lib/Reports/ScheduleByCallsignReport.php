<?php

namespace App\Lib\Reports;

use App\Models\PersonSlot;
use App\Models\Slot;
use Illuminate\Support\Facades\DB;

class ScheduleByCallsignReport
{
    /*
     * Schedule By Callsign report
     */

    public static function execute(int $year)
    {
        $peopleGroups = DB::table('person_slot')
            ->select('person_slot.*',
                'person.callsign',
                'slot.begins',
                'slot.ends',
                'slot.description',
                'slot.position_id',
                'position.title as position_title',
                'position.active as position_active'
            )
            ->join('slot', 'slot.id', 'person_slot.slot_id')
            ->join('person', 'person.id', 'person_slot.person_id')
            ->join('position', 'position.id', 'slot.position_id')
            ->whereYear('slot.begins', $year)
            ->orderBy('callsign')
            ->orderBy('slot.begins')
            ->get()
            ->groupBy('person_id');

        return $peopleGroups->map(function ($signups) {
            return [
                'id'    => $signups[0]->person_id,
                'callsign' => $signups[0]->callsign,
                'slots' => $signups->map(function ($row) {
                    return [
                        'position'  => [
                            'id'     => $row->position_id,
                            'title'  => $row->position_title,
                            'active' => $row->position_active,
                        ],
                        'begins'      => (string) $row->begins,
                        'ends'        => (string) $row->ends,
                        'description' => $row->description,
                    ];
                })->values()
            ];
        })->values();
    }
}