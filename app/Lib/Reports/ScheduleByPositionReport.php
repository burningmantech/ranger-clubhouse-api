<?php


namespace App\Lib\Reports;


use App\Models\Slot;
use Illuminate\Support\Collection;

class ScheduleByPositionReport
{
    /**
     * Report on all scheduled sign up by position for a given year
     *
     * @param int $year
     * @return Collection
     */

    public static function execute(int $year)
    {
        $rows = Slot::select('slot.*')
            ->join('position', 'position.id', 'slot.position_id')
            ->whereYear('begins', $year)
            ->with(['position:id,title,active', 'person_slot.person:id,callsign,status'])
            ->orderBy('position.title')
            ->orderBy('slot.begins')
            ->get()
            ->groupBy('position_id');


        return $rows->map(function ($p) {
            $slot = $p[0];
            $position = $slot->position;

            return [
                'id' => $position->id,
                'title' => $position->title,
                'active' => $position->active,
                'slots' => $p->map(function ($slot) {
                    $signups = $slot->person_slot
                        ->sort(fn($a, $b) => strcasecmp($a->person->callsign, $b->person->callsign))
                        ->values();

                    return [
                        'id' => $slot->id,
                        'begins' => (string)$slot->begins,
                        'ends' => (string)$slot->ends,
                        'active' => $slot->active,
                        'description' => (string)$slot->description,
                        'max' => $slot->max,
                        'sign_ups' => $signups->map(function ($row) {
                            $person = $row->person;
                            return [
                                'id' => $row->person_id,
                                'callsign' => $person->callsign
                            ];
                        })
                    ];
                })->values()
            ];
        })->values();
    }
}