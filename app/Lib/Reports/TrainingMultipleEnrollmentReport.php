<?php


namespace App\Lib\Reports;

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
        $rows = DB::select('SELECT
                  p.id
                FROM slot s
                  LEFT JOIN person_slot AS ps ON ps.slot_id=s.id
                  LEFT JOIN person        AS p  ON ps.person_id=p.id
                WHERE YEAR(s.begins) = ? AND s.position_id = ?
                GROUP BY p.id
                HAVING COUNT(s.id) > 1', [$year, $positionId]);

        $multipleIds = array_column($rows, 'id');
        $byPerson = DB::table('person')
            ->select(
                'person.id as person_id',
                'person.callsign',
                'person.first_name',
                'person.last_name',
                'person.email',
                'slot.begins AS date',
                'slot.description AS location',
                'slot.id as slot_id'
            )
            ->leftJoin('person_slot', 'person_slot.person_id', '=', 'person.id')
            ->leftJoin('slot', 'slot.id', '=', 'person_slot.slot_id')
            ->leftJoin('position', 'position.id', '=', 'slot.position_id')
            ->where('slot.begins_year', $year)
            ->where('position.id', $positionId)
            ->whereIntegerInRaw('person.id', $multipleIds)
            ->orderBy('person.callsign', 'asc')
            ->orderBy('date', 'ASC')
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

                    if (Slot::isPartOfSessionGroup($slot->location, $check->location)) {
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

            if ($haveMultiples == false) {
                continue;
            }

            $person = $slots[0];
            $people[] = [
                'person_id' => $personId,
                'callsign' => $person->callsign,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'email' => $person->email,
                'enrollments' => $slots->map(function ($row) {
                    return [
                        'slot_id' => $row->slot_id,
                        'date' => $row->date,
                        'location' => $row->location,
                    ];
                })->values()
            ];
        }

        return $people;
    }
}