<?php


namespace App\Lib\Reports;


use App\Models\Slot;

class ScheduleByPositionReport
{
    /**
     * Report on all scheduled sign up by position for a given year
     *
     * @param int $year
     * @return array
     */

    public static function execute(int $year, $canViewEmail): array
    {
        $rows = Slot::select('slot.*')
            ->join('position', 'position.id', 'slot.position_id')
            ->whereYear('begins', $year)
            ->with(['position:id,title,active', 'person_slot.person:id,callsign,first_name,last_name,status,email'])
            ->orderBy('position.title')
            ->orderBy('slot.begins')
            ->get()
            ->groupBy('position_id');

        $people = [];

        $positions = $rows->map(function ($p) use ($canViewEmail, &$people) {
            $slot = $p[0];
            $position = $slot->position;

            return [
                'id' => $position->id,
                'title' => $position->title,
                'active' => $position->active,
                'slots' => $p->map(function ($slot) use ($canViewEmail, &$people) {
                    return [
                        'id' => $slot->id,
                        'begins' => (string)$slot->begins,
                        'ends' => (string)$slot->ends,
                        'active' => $slot->active,
                        'description' => (string)$slot->description,
                        'max' => $slot->max,
                        'sign_ups' =>  $slot->person_slot->map(function ($row) use ($canViewEmail, &$people) {
                            $person = $row->person;
                            $personId = $person->id;
                            $people[$personId] ??= [
                                'id' => $personId,
                                'callsign' => $person->callsign,
                                'first_name' => $person->first_name,
                                'last_name' => $person->last_name,
                                'status' => $person->status,
                            ];
                            if ($canViewEmail) {
                                $people[$personId]['email'] ??= $person->email;
                            }
                            return $personId;
                        })->toArray()
                    ];
                })->values()->toArray()
            ];
        })->values()->toArray();

        return [
            'positions' => $positions,
            'people'=> $people
        ];
    }
}