<?php


namespace App\Lib\Reports;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class TrainingCompletedReport
{
    /**
     * Find everyone who has completed training in a year
     *
     * The return structure is:
     *   slot_id: slot record
     *   slot_description: the description
     *   slot_begins: slot start datetime
     *   people: array of who completed training
     *         (id, first_name, last_name, email)
     *
     * @param int $year which year to look at
     * @return array people who have completed training.
     */

    public static function execute($position, int $year): array
    {
        // TODO: extend to support multiple training positions

        $positionIds = [$position->id];
        if ($position->id == Position::HQ_FULL_TRAINING) {
            $positionIds[] = Position::HQ_REFRESHER_TRAINING;
        }
        $positionIds = implode(',', $positionIds);

        $rows = DB::select(
            "SELECT
                    person.id,
                    callsign,
                    first_name,
                    preferred_name,
                    last_name,
                    email,
                    slot.id as slot_id,
                    slot.description as slot_description,
                    slot.begins as slot_begins
                FROM slot
                JOIN trainee_status ON slot.id=trainee_status.slot_id
                JOIN person ON trainee_status.person_id=person.id
                WHERE YEAR(slot.begins) = :year
                    AND slot.position_id IN ($positionIds)
                    AND passed = 1
                 ORDER BY slot.begins, description, callsign",
            [
                'year' => $year
            ]
        );

        $slots = [];
        $slotsByIds = [];

        foreach ($rows as $person) {
            $slotId = $person->slot_id;

            if (!isset($slotsByIds[$slotId])) {
                $slot = [
                    'slot_id' => $slotId,
                    'slot_description' => $person->slot_description,
                    'slot_begins' => $person->slot_begins,
                    'people' => []
                ];

                $slotsByIds[$slotId] = &$slot;
                $slots[] = &$slot;
                unset($slot);
            }

            $slotsByIds[$slotId]['people'][] = [
                'id' => $person->id,
                'first_name' => $person->first_name,
                'preferred_name' => $person->preferred_name,
                'last_name' => $person->last_name,
                'callsign' => $person->callsign,
                'email' => $person->email
            ];
        }

        return $slots;
    }
}