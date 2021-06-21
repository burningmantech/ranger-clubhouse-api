<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use App\Models\Slot;
use Illuminate\Support\Facades\DB;

class TrainingUntrainedPeopleReport
{
    /**
     * Find all the people who have an ART position(s) and who have not signed up
     * or completed training.
     *
     * @param $position
     * @param int $year
     * @return array
     */

    public static function execute($position, int $year): array
    {
        $trainedPositionIds = Position::where('training_position_id', $position->id)->pluck('id');
        if ($trainedPositionIds->isEmpty()) {
            return [
                'not_signed_up' => [],
                'not_passed' => [],
            ];
        }

        $positionIds = [$position->id];

        if ($position->id == Position::HQ_FULL_TRAINING) {
            $positionIds[] = Position::HQ_REFRESHER_TRAINING;
        }

        $trainingSlotIds = Slot::whereIn('position_id', $positionIds)->whereYear('begins', $year)->pluck('id');

        if ($trainingSlotIds->isEmpty()) {
            return [
                'not_signed_up' => [],
                'not_passed' => [],
            ];
        }

        /*
         * Find those who did not sign up for a training position, include
         * the slot & position title for a detail report.
         * (yah, not crazy about the sub-selects here.)
         */

        $positionIds = implode(',', $positionIds);
        $rows = DB::select("SELECT
                person_slot.person_id,
                person_slot.slot_id,
                slot.description,
                slot.begins,
                position.title
            FROM person_slot
            INNER JOIN slot ON slot.id=person_slot.slot_id
            INNER JOIN position ON position.id = slot.position_id
            WHERE person_slot.slot_id IN (
                SELECT id FROM slot WHERE position_id IN (" .
            $trainedPositionIds->implode(',') . "
                ) AND YEAR(begins)=$year
              )
             AND person_slot.person_id NOT IN (
                    SELECT person_id FROM person_slot
                     WHERE slot_id IN
                       (SELECT id FROM slot WHERE position_id IN ($positionIds) AND YEAR(begins)=$year)
               )
            ORDER BY slot.begins asc");

        $peopleSignedUp = [];
        foreach ($rows as $row) {
            $personId = $row->person_id;
            if (!isset($peopleSignedUp[$personId])) {
                $peopleSignedUp[$personId] = [];
            }
            $peopleSignedUp[$personId][] = $row;
        }

        $untrainedSignedup = [];
        if (!empty($peopleSignedUp)) {
            $rows = Person::select('id', 'callsign', 'first_name', 'last_name', 'email')
                ->whereIn('id', array_keys($peopleSignedUp))->get();
            foreach ($rows as $row) {
                $row->slots = array_map(function ($slot) {
                    return [
                        'slot_id' => $slot->slot_id,
                        'begins' => $slot->begins,
                        'description' => $slot->description,
                        'title' => $slot->title,
                    ];
                }, $peopleSignedUp[$row->id]);
                $untrainedSignedup[] = $row;
            }
            usort($untrainedSignedup, fn($a, $b) => strcasecmp($a->callsign, $b->callsign));
        }

        /*
         * Find those signed up for a training shift, and did not pass (yet)
         * (this list will be filtered later below when looking for the
         * regular shifts)
         */

        $rows = DB::select(
            "SELECT
                    person.id,
                    person.callsign,
                    person.email,
                    person.first_name,
                    person.last_name,
                    slot.description as training_description,
                    slot.begins as training_begins,
                    slot.id as training_slot_id
            FROM person_slot
            INNER JOIN slot ON person_slot.slot_id=slot.id
            INNER JOIN person ON person.id=person_slot.person_id
            LEFT JOIN trainee_status ON person_slot.slot_id=trainee_status.slot_id AND person_slot.person_id=trainee_status.person_id
            WHERE person_slot.slot_id IN (" . $trainingSlotIds->implode(',') . ")
            AND trainee_status.passed != TRUE
            AND NOT EXISTS (SELECT 1 FROM trainee_status ts WHERE ts.slot_id=person_slot.slot_id AND person_slot.person_id=ts.person_id AND ts.passed IS TRUE LIMIT 1)"
        );

        $peopleNotPassed = collect($rows)->keyBy('id');

        $untrainedNotPassed = [];
        if (!$peopleNotPassed->isEmpty()) {
            $peopleInfo = [];
            $personIds = $peopleNotPassed->keys();

            //
            // Find which shifts the person signed up for.
            //
            $rows = DB::select(
                "SELECT
                     person_slot.person_id,
                     person_slot.slot_id,
                     slot.description,
                     slot.begins,
                     position.title as position_title
                 FROM person_slot
                 INNER JOIN slot ON slot.id=person_slot.slot_id
                 INNER JOIN position ON position.id = slot.position_id
                 WHERE person_slot.person_id IN (" . $personIds->implode(',') . ")
                 AND  person_slot.slot_id IN (
                     SELECT id FROM slot WHERE position_id IN (" .
                $trainedPositionIds->implode(',') .
                ") AND YEAR(begins)=$year
                   )
                 ORDER BY person_slot.person_id asc,slot.begins asc"
            );

            foreach ($rows as $row) {
                $personId = $row->person_id;
                if (empty($peopleNotPassed[$personId]->slots)) {
                    $peopleNotPassed[$personId]->slots = [];
                }
                $peopleNotPassed[$personId]->slots[] = $row;
            }

            // Filter out those who have a training shift but no shifts
            foreach ($peopleNotPassed as $personId => $row) {
                if (isset($row->slots)) {
                    $untrainedNotPassed[$row->callsign] = $row;
                }
            }

            ksort($untrainedNotPassed, SORT_NATURAL | SORT_FLAG_CASE);
            $untrainedNotPassed = array_values($untrainedNotPassed);
        }

        return [
            'not_signed_up' => $untrainedSignedup,
            'not_passed' => $untrainedNotPassed,
        ];
    }
}