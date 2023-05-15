<?php

namespace App\Lib\Reports;

use App\Models\Position;
use App\Models\Slot;

class TrainerAttendanceReport
{
    /**
     * Retrieve all trainers and their attendance for a given year
     *
     * @param $position
     * @param int $year
     * @return array
     */

    public static function execute($position, int $year): array
    {

        $teachingPositions = Position::TRAINERS[$position->id] ?? null;

        if (!$teachingPositions) {
            return [];
        }

        $slots = Slot::where('begins_year', $year)
            ->whereIn('position_id', $teachingPositions)
            ->with(['position:id,title', 'person_slot.person:id,callsign', 'trainer_slot'])
            ->orderBy('begins')
            ->get();

        $trainers = [];

        foreach ($slots as $slot) {
            // Find the training slot that begins within a hour of the slot start time.
            $trainingSlot = Slot::where('description', $slot->description)
                ->whereRaw('begins BETWEEN DATE_SUB(?, INTERVAL 1 HOUR) AND ?', [$slot->begins, $slot->ends])
                ->where('position_id', $position->id)
                ->first();

            if ($trainingSlot == null) {
                continue;
            }

            foreach ($slot->person_slot as $ps) {
                if (!isset($trainers[$ps->person_id])) {
                    $trainers[$ps->person_id] = (object)[
                        'id' => $ps->person_id,
                        'callsign' => $ps->person ? $ps->person->callsign : 'Person #' . $ps->person_id,
                        'slots' => []
                    ];
                }

                $trainer = $trainers[$ps->person_id];
                $ts = $slot->trainer_status->firstWhere('person_id', $ps->person_id);
                $trainer->slots[] = (object)[
                    'id' => $slot->id,
                    'begins' => (string)$slot->begins,
                    'description' => $slot->description,
                    'position_title' => $slot->position->title,
                    'status' => $ts ? $ts->status : 'pending',
                    'training_slot_id' => $trainingSlot->id,
                ];
            }
        }

        usort($trainers, fn($a, $b) => strcasecmp($a->callsign, $b->callsign));

        return $trainers;
    }
}