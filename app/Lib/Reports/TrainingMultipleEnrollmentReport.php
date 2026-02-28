<?php


namespace App\Lib\Reports;

use App\Models\Position;
use App\Models\Slot;
use Illuminate\Support\Facades\DB;

class TrainingMultipleEnrollmentReport
{
    /**
     * Find multiple enrollments for a training
     *
     * The method returns an array:
     * person_id: person found
     * callsign, first_name, loast_name, email
     * enrollments: array
     *     slot_id: session signed up for
     *     date: begins date of session
     *     location: location of sessions
     *
     * @param int $year which year to find the multiple enrollments
     * @return array of people who are enrolled multiple times.
     */

    public static function execute($position, int $year): array
    {
        $positionId = $position->id;

        $positionsId = [$positionId];
        $menteePositions = Position::ART_GRADUATE_TO_POSITIONS[$positionId]['positions'] ?? null;
        if ($menteePositions) {
            $positionsId = [...$positionsId, ...$menteePositions];
        }

        $enrollments = [];
        foreach ($positionsId as $positionId) {
            $multipleIds = DB::table('slot')
                ->join('person_slot', 'person_slot.slot_id', '=', 'slot.id')
                ->join('person', 'person_slot.person_id', '=', 'person.id')
                ->where('slot.begins_year', $year)
                ->where('slot.position_id', $positionId)
                ->groupBy('person.id')
                ->havingRaw('COUNT(slot.id) > 1')
                ->pluck('person.id')
                ->toArray();
            $byPerson = DB::table('person')
                ->select(
                    'person.id as person_id',
                    'person.callsign',
                    'person.first_name',
                    'person.last_name',
                    'person.email',
                    'slot.begins AS begins',
                    'slot.description AS description',
                    'slot.id as slot_id',
                    'slot.position_id',
                )
                ->leftJoin('person_slot', 'person_slot.person_id', '=', 'person.id')
                ->leftJoin('slot', 'slot.id', '=', 'person_slot.slot_id')
                ->where('slot.begins_year', $year)
                ->where('slot.position_id', $positionId)
                ->whereIntegerInRaw('person.id', $multipleIds)
                ->orderBy('person.callsign')
                ->orderBy('begins', 'ASC')
                ->get()
                ->groupBy('person_id');

            $people = [];
            foreach ($byPerson as $personId => $slots) {
                foreach ($slots as $slot) {
                    $slot->isMultiParter = false;
                }

                $haveMultiples = false;

                foreach ($slots as $check) {
                    if ($check->isMultiParter) {
                        continue;
                    }

                    foreach ($slots as $slot) {
                        if ($slot->isMultiParter || $slot->slot_id == $check->slot_id) {
                            continue;
                        }

                        if (Slot::isPartOfSessionGroup($slot->description, $check->description)) {
                            $slot->isMultiParter = true;
                            $check->isMultiParter = true;
                            break;
                        }
                    }

                    if (!$check->isMultiParter) {
                        $haveMultiples = true;
                        break;
                    }
                }

                if (!$haveMultiples) {
                    continue;
                }

                $person = $slots[0];
                $people[] = [
                    'person_id' => $personId,
                    'callsign' => $person->callsign,
                    'first_name' => $person->first_name,
                    'last_name' => $person->last_name,
                    'email' => $person->email,
                    'enrollments' => $slots->map(fn($row) => [
                        'slot_id' => $row->slot_id,
                        'begins' => $row->begins,
                        'description' => $row->description,
                    ]
                    )->values()
                ];
            }

            $enrollments[] = [
                'people' => $people,
                'position_id' => $positionId,
                'position_title' => $positionId == $position->id ? $position->title : Position::retrieveTitle($positionId),
            ];
        }

        return $enrollments;
    }
}