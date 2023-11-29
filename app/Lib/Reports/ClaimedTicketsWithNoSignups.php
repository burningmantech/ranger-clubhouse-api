<?php

namespace App\Lib\Reports;

use App\Models\AccessDocument;
use App\Models\Position;
use App\Models\Slot;
use App\Models\TrainerStatus;
use Illuminate\Support\Facades\DB;

class ClaimedTicketsWithNoSignups
{
    public static function execute(): array
    {
        $claimedRows = AccessDocument::whereIn('type', [AccessDocument::SPT, AccessDocument::STAFF_CREDENTIAL])
            ->whereIn('status', [AccessDocument::CLAIMED, AccessDocument::SUBMITTED])
            ->with('person:id,callsign,status,first_name,preferred_name,last_name,email')
            ->get();

        if ($claimedRows->isEmpty()) {
            return [];
        }

        $peopleIds = $claimedRows->pluck('person_id')->unique()->values();
        $year = current_year();

        // Find non-training sign-ups
        $signUps = DB::table('slot')
            ->select('person_slot.person_id')
            ->join('person_slot', 'slot.id', 'person_slot.slot_id')
            ->join('position', 'position.id', 'slot.position_id')
            ->where('position.type', '!=', Position::TYPE_TRAINING)
            ->where('begins_year', current_year())
            ->where('begins', '>=', "$year-08-15")
            ->whereIntegerInRaw('person_slot.person_id', $peopleIds)
            ->groupBy('person_slot.person_id')
            ->get()
            ->keyBy('person_id');

        $timesheets = DB::table('timesheet')
            ->select('person_id')
            ->whereYear('on_duty', $year)
            ->whereIntegerInRaw('person_id', $peopleIds)
            ->groupBy('person_id')
            ->get()
            ->keyBy('person_id');

        $ocPeople = DB::table('person_online_course')
            ->whereIntegerInRaw('person_id', $peopleIds)
            ->where('year', $year)
            ->where('position_id', Position::TRAINING)
            ->get()
            ->keyBy('person_id');

        $trainings = Slot::select('slot.*', 'person_slot.person_id', 'trainee_status.passed')
            ->join('person_slot', 'slot.id', 'person_slot.slot_id')
            ->leftJoin('trainee_status', function ($j) {
                $j->on('trainee_status.slot_id', 'slot.id');
                $j->whereColumn('trainee_status.person_id', 'person_slot.person_id');
            })
            ->where('slot.active', true)
            ->where('slot.position_id', Position::TRAINING)
            ->where('slot.begins_year', current_year())
            ->whereIntegerInRaw('person_slot.person_id', $peopleIds)
            ->get()
            ->groupBy('person_id');

        $trainers = Slot::select('slot.*', 'person_slot.person_id', 'trainer_status.status as trainer_status')
            ->join('person_slot', 'slot.id', 'person_slot.slot_id')
            ->leftJoin('trainer_status', function ($j) {
                $j->on('trainer_status.trainer_slot_id', 'slot.id');
                $j->whereColumn('trainer_status.person_id', 'person_slot.person_id');
            })
            ->where('slot.active', true)
            ->where('slot.position_id', Position::TRAINER)
            ->where('slot.begins_year', current_year())
            ->whereIntegerInRaw('person_slot.person_id', $peopleIds)
            ->get()
            ->groupBy('person_id');

        $people = [];
        foreach ($claimedRows as $claimed) {
            $person = $claimed->person;
            $personId = $person->id;
            $ocCompleted = $ocPeople->has($personId);
            $hasSignups = $signUps->has($personId);
            $didWork = $timesheets->has($personId);

            if ($ocCompleted && $didWork) {
                // Person is cool, OT was completed and did work on playa.
                continue;
            }

            $trained = $trainings->get($personId);
            $trainer = $trainers->get($personId);

            $didTrain = false;
            $didTeach = false;

            if ($trained) {
                foreach ($trained as $slot) {
                    if ($slot->passed) {
                        $didTrain = true;
                        break;
                    }
                }
            }

            if ($trainer) {
                foreach ($trainer as $slot) {
                    if ($slot->trainer_status == TrainerStatus::ATTENDED) {
                        $didTeach = true;
                        break;
                    }
                }
            }

            $people[] = [
                'id' => $personId,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'email' => $person->email,
                'first_name' => $person->first_name,
                'preferred_name'=> $person->preferred_name,
                'last_name' => $person->last_name,
                'access_document_id' => $claimed->id,
                'type' => $claimed->type,
                'ot_completed' => $ocCompleted,
                'has_signups' => $hasSignups,
                'did_work' => $didWork,
                'have_trained' => ($didTrain || $didTeach),
                'did_teach' => $didTeach,
                'trainings' => $trained?->map(fn($t) => [
                    'id' => $t->id,
                    'begins' => (string)$t->begins,
                    'status' => ($t->has_ended ? ($t->passed ? 'passed' : 'not-passed') : 'pending')
                ]
                )->values()->toArray(),
                'teachings' => $trainer?->map(fn($t) => [
                    'id' => $t->id,
                    'begins' => (string)$t->begins,
                    'status' => ($t->has_ended ? $t->trainer_status : 'pending')]
                )->values()->toArray(),
            ];
        }

        usort($people, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
        return $people;
    }
}