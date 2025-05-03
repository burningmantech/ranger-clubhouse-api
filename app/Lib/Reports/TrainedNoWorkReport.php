<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use App\Models\TrainerStatus;
use Illuminate\Support\Facades\DB;

class TrainedNoWorkReport
{
    public static function execute(int $positionId, int $year): array
    {
        $trainerPositionIds = Position::TRAINERS[$positionId] ?? null;

        if ($trainerPositionIds) {
            $workingPositionIds = [$positionId, ...$trainerPositionIds];
        } else {
            $workingPositionIds = [$positionId];
        }

        $sql = DB::table('position')
            ->whereNotIn('id', $workingPositionIds)
            ->where('active', true)
            ->where('no_training_required', false)
            ->where('type', '!=', Position::TYPE_TRAINING);

        if ($positionId != Position::TRAINING) {
            $sql->where('training_position_id', $positionId);
        }

        $positionIds = $sql->pluck('id');

        if ($positionIds->isEmpty()) {
            // Hmmm, not good. New position that hasn't been fully set up yet?
            return [
                'people' => [],
                'no_positions' => []
            ];
        }

        $slotIds = DB::table('slot')
            ->where('active', true)
            ->where('begins_year', $year)
            ->whereIn('position_id', $positionIds)
            ->pluck('slot.id');

        if ($slotIds->isEmpty()) {
            // Might be too early in the event cycle.
            return [
                'people' => [],
                'no_slots' => true
            ];
        }

        // Find folks who passed training
        $peopleIds = DB::table('slot')
            ->join('person_slot', 'person_slot.slot_id', '=', 'slot.id')
            ->join('trainee_status', function ($j) {
                $j->on('trainee_status.slot_id', '=', 'slot.id');
                $j->whereColumn('trainee_status.person_id', 'person_slot.person_id');
            })
            ->where('slot.position_id', $positionId)
            ->where('slot.active', true)
            ->where('slot.begins_year', $year)
            ->where('trainee_status.passed', true)
            ->pluck('person_slot.person_id');

        // Find the trainers who taught
        if ($trainerPositionIds) {
            $trainers = DB::table('slot')
                ->join('person_slot', 'person_slot.id', '=', 'slot.id')
                ->join('trainer_status', function ($j) {
                    $j->on('trainer_status.slot_id', '=', 'trainer_status.trainer_slot_id');
                    $j->whereColumn('trainer_status.person_id', 'person_slot.person_id');
                })
                ->whereIn('slot.position_id', $trainerPositionIds)
                ->where('slot.active', true)
                ->where('slot.begins_year', $year)
                ->where('trainer_status.status', TrainerStatus::ATTENDED)
                ->pluck('person_slot.person_id');
            $peopleIds = $trainers->merge($peopleIds);
        }

        $peopleIds = $peopleIds->unique();
        if ($peopleIds->isEmpty()) {
            return ['people' => [], 'no_trained' => true];
        }

        $people = DB::table('person')
            ->select('person.id', 'person.callsign', 'person.status')
            ->whereIn('person.id', $peopleIds)
            ->whereIn('person.status', Person::ACTIVE_STATUSES)
            ->whereNotExists(function ($q) use ($positionIds, $year) {
                $q->from('timesheet')
                    ->select(DB::raw(1))
                    ->whereColumn('person_id', 'person.id')
                    ->whereYear('on_duty', $year)
                    ->whereIn('position_id', $positionIds)
                    ->limit(1);
            })
            ->orderBy('person.callsign')
            ->get();

        return ['people' => $people->toArray()];
    }
}
