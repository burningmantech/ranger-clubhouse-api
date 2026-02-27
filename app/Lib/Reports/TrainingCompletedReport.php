<?php


namespace App\Lib\Reports;

use App\Models\Position;
use App\Models\Slot;
use App\Models\TraineeStatus;
use App\Models\TrainerStatus;

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
     *   trainers: array of trainers who attended
     *         (id, first_name, last_name, callsign, email)
     *
     * @param int $year which year to look at
     * @return array people who have completed training.
     */

    public static function execute($position, int $year): array
    {
        // TODO: extend to support multiple training positions

        $trainingPositionIds = [$position->id];
        if ($position->id == Position::HQ_FULL_TRAINING) {
            $trainingPositionIds[] = Position::HQ_REFRESHER_TRAINING;
        }

        $rows = TraineeStatus::query()
            ->join('slot', 'slot.id', '=', 'trainee_status.slot_id')
            ->join('person', 'person.id', '=', 'trainee_status.person_id')
            ->whereYear('slot.begins', $year)
            ->whereIn('slot.position_id', $trainingPositionIds)
            ->where('trainee_status.passed', true)
            ->select([
                'person.id',
                'person.callsign',
                'person.first_name',
                'person.preferred_name',
                'person.last_name',
                'person.email',
                'slot.id as slot_id',
                'slot.description as slot_description',
                'slot.begins as slot_begins',
            ])
            ->orderBy('slot.begins')
            ->orderBy('slot.description')
            ->orderBy('person.callsign')
            ->get();

        $slots = [];
        $slotsByIds = [];

        foreach ($rows as $person) {
            $slotId = $person->slot_id;

            if (!isset($slotsByIds[$slotId])) {
                $slot = [
                    'slot_id' => $slotId,
                    'slot_description' => $person->slot_description,
                    'slot_begins' => $person->slot_begins,
                    'people' => [],
                    'trainers' => [],
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
                'email' => $person->email,
            ];
        }

        // Find trainers who attended each training session
        $trainerPositionIds = collect($trainingPositionIds)
            ->flatMap(fn($id) => Position::TRAINERS[$id] ?? [])
            ->unique()
            ->values()
            ->toArray();

        if (!empty($trainerPositionIds) && !empty($slotsByIds)) {
            $trainerRows = Slot::query()
                ->from('slot as training_slot')
                ->join('slot as trainer_slot', function ($join) use ($trainerPositionIds) {
                    $join->on('trainer_slot.description', '=', 'training_slot.description')
                        ->whereIn('trainer_slot.position_id', $trainerPositionIds)
                        ->whereRaw('trainer_slot.begins BETWEEN DATE_SUB(training_slot.begins, INTERVAL 1 HOUR) AND training_slot.ends');
                })
                ->join('trainer_status as ts', function ($join) {
                    $join->on('ts.trainer_slot_id', '=', 'trainer_slot.id')
                        ->where('ts.status', TrainerStatus::ATTENDED);
                })
                ->join('person', 'person.id', '=', 'ts.person_id')
                ->whereYear('training_slot.begins', $year)
                ->whereIn('training_slot.position_id', $trainingPositionIds)
                ->select([
                    'training_slot.id as slot_id',
                    'person.id',
                    'person.callsign',
                    'person.first_name',
                    'person.preferred_name',
                    'person.last_name',
                    'person.email',
                ])
                ->orderBy('training_slot.begins')
                ->orderBy('training_slot.description')
                ->orderBy('person.callsign')
                ->get();

            foreach ($trainerRows as $row) {
                if (isset($slotsByIds[$row->slot_id])) {
                    $slotsByIds[$row->slot_id]['trainers'][] = [
                        'id' => $row->id,
                        'first_name' => $row->first_name,
                        'preferred_name' => $row->preferred_name,
                        'last_name' => $row->last_name,
                        'callsign' => $row->callsign,
                        'email' => $row->email,
                    ];
                }
            }
        }

        return $slots;
    }
}
