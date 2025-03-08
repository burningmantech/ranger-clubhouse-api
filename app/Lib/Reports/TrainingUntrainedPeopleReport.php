<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use App\Models\Slot;
use App\Models\TrainerStatus;
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
        $positionsRequiringTrainingIds = Position::where('training_position_id', $position->id)->pluck('id');
        if ($positionsRequiringTrainingIds->isEmpty()) {
            return [
                'not_signed_up' => [],
                'not_passed' => [],
            ];
        }

        $trainingIds = [$position->id];

        if ($position->id == Position::HQ_FULL_TRAINING) {
            $trainingIds[] = Position::HQ_REFRESHER_TRAINING;
        }

        $trainerPositionIds = Position::TRAINERS[$position->id] ?? [];

        $trainingSlotIds = Slot::whereIn('position_id', [...$trainingIds, ...$trainerPositionIds])
            ->where('begins_year', $year)
            ->where('active', true)
            ->pluck('id');

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


        $requireTrainingSlotIds = Slot::whereIn('position_id', $positionsRequiringTrainingIds)
            ->where('begins_year', $year)
            ->where('active', true)
            ->pluck('id');

        $rows = DB::table('person_slot')
            ->select('person_slot.person_id', 'person_slot.slot_id', 'slot.description', 'slot.begins', 'position.title')
            ->join('slot', 'person_slot.slot_id', '=', 'slot.id')
            ->join('position', 'slot.position_id', '=', 'position.id')
            ->whereIn('person_slot.slot_id', $requireTrainingSlotIds)
            ->whereNotIn('person_slot.person_id', function ($query) use ($trainingSlotIds) {
                $query->from('person_slot')
                    ->select('person_slot.person_id')
                    ->whereIn('person_slot.slot_id', $trainingSlotIds);
            })
            ->orderBy('slot.begins')
            ->get();

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
            $rows = Person::select('id', 'callsign', 'first_name', 'preferred_name', 'last_name', 'email')
                ->whereIntegerInRaw('id', array_keys($peopleSignedUp))
                ->get();
            foreach ($rows as $row) {
                $row->slots = array_map(fn($slot) => [
                    'slot_id' => $slot->slot_id,
                    'begins' => $slot->begins,
                    'description' => $slot->description,
                    'title' => $slot->title,
                ], $peopleSignedUp[$row->id]);
                $untrainedSignedup[] = $row;
            }
            usort($untrainedSignedup, fn($a, $b) => strcasecmp($a->callsign, $b->callsign));
        }

        /*
         * Find those signed up for a training shift, and did not pass (yet)
         * (this list will be filtered later below when looking for the
         * regular shifts)
         */

        $rows = DB::table('person_slot')
            ->select(
                'person.id',
                'person.callsign',
                'person.email',
                'person.first_name',
                'person.preferred_name',
                'person.last_name',
                'slot.description as training_description',
                'slot.begins as training_begins',
                'slot.id as training_slot_id',
            )->join('slot', 'person_slot.slot_id', '=', 'slot.id')
            ->join('person', 'person.id', '=', 'person_slot.person_id')
            ->leftJoin('trainee_status', function ($j) {
                $j->on('person_slot.person_id', 'trainee_status.person_id')
                    ->whereColumn('person_slot.slot_id', 'trainee_status.slot_id');
            })->whereIn('person_slot.slot_id', $trainingSlotIds)
            ->where(function ($q) {
                $q->whereNull('trainee_status.passed')
                    ->orWhere('trainee_status.passed', false);
            })
            ->whereNotExists(function ($q) use ($trainingSlotIds) {
                $q->from('trainee_status as ts')
                    ->select(DB::raw(1))
                    ->whereColumn('ts.person_id', 'person_slot.person_id')
                    ->whereIn('ts.slot_id', $trainingSlotIds)
                    ->where('ts.passed', '=', true)
                    ->limit(1);
            })->get();

        $peopleNotPassed = $rows->keyBy('id');

        if (!empty($trainerPositionIds)) {
            $trainerSlotsIds = DB::table('slot')
                ->whereIn('position_id', $trainerPositionIds)
                ->where('begins_year', $year)
                ->where('active', true)
                ->pluck('id');

            $taught = DB::table('person_slot')
                ->select('trainer_status.person_id')
                ->join('slot', 'slot.id', 'person_slot.slot_id')
                ->join('trainer_status', 'person_slot.slot_id', 'trainer_status.trainer_slot_id')
                ->whereIn('person_slot.slot_id', $trainerSlotsIds)
                ->where('trainer_status.status', TrainerStatus::ATTENDED)
                ->distinct()
                ->get()
                ->keyBy('person_id');

            $peopleNotPassed = $peopleNotPassed->filter(fn($person) => !$taught->has($person->id));
        }

        $untrainedNotPassed = [];
        if (!$peopleNotPassed->isEmpty()) {
            $personIds = $peopleNotPassed->keys();

            //
            // Find which shifts the person signed up for.
            //
            $rows = DB::table('person_slot')
                ->select(
                    'person_slot.person_id',
                    'person_slot.slot_id',
                    'slot.description',
                    'slot.begins',
                    'position.title as position_title')
                ->join('slot', 'person_slot.slot_id', '=', 'slot.id')
                ->join('position', 'slot.position_id', '=', 'position.id')
                ->whereIn('person_slot.person_id', $personIds)
                ->whereIn('person_slot.slot_id', $requireTrainingSlotIds)
                ->orderBy('person_slot.person_id')
                ->orderBy('slot.begins')
                ->get();

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