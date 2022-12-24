<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\PersonStatus;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class TrainingSlotCapacityReport
{

    /**
     * Find the signups for all training shifts in a given year
     *
     * The method returns:
     *
     * slot_id: slot record id
     * description: slot description
     * date: starts on datetime
     * max: sign up limit
     * signed_up: total signup count
     * filled: how full in a percentage (0 to 100+)
     * pnv_count: alpha & prospective count
     * veteran_count: signups who are not alpha, auditor or prospective
     * auditor_count: signups who are auditors
     *
     * @param $position
     * @param int $year the year to search
     * @return array
     */

    public static function execute($position, int $year): array
    {
        $positionIds = [$position->id];
        if ($position->id == Position::HQ_FULL_TRAINING) {
            $positionIds[] = Position::HQ_REFRESHER_TRAINING;
        }

        $trainerPositions = Position::TRAINERS[$position->id];
        $rows = DB::table('slot')
            ->select('id', 'description', 'begins', 'ends', 'max')
            ->whereYear('slot.begins', $year)
            ->whereIn('slot.position_id', $positionIds)
            ->where('active', 1)
            ->orderBy('slot.begins')
            ->get();

        $trainerIds = [];
        foreach ($rows as $row) {
            $ids = DB::table('person_slot')
                ->where('slot_id', $row->id)
                ->pluck('person_id');

            $row->signed_up = $ids->count();

            if ($row->signed_up > 0 && $row->max > 0) {
                $row->filled = round(($row->signed_up / $row->max) * 100);
            } else {
                $row->filled = 0;
            }

            if ($ids->isEmpty()) {
                continue;
            }

            $statuses = PersonStatus::findStatusForIdsTime($ids->toArray(), $row->begins);
            $alphas = 0;
            $vets = 0;
            $auditors = 0;

            foreach ($ids as $personId) {
                $status = $statuses->get($personId);
                if ($status) {
                    switch ($status->new_status) {
                        case Person::AUDITOR:
                            $auditors++;
                            break;
                        case Person::ALPHA:
                        case Person::BONKED:
                        case Person::PAST_PROSPECTIVE:
                        case Person::PROSPECTIVE:
                        case Person::PROSPECTIVE_WAITLIST:
                        case Person::UBERBONKED:
                            $alphas++;
                            break;
                        default:
                            $vets++;
                            break;
                    }
                } else {
                    $vets++;
                }
            }
            // Just in case the status records could not be found for everyone, dump
            // the difference into the vets column
            $vets += $ids->count() - $statuses->count();

            $row->pnv_count = $alphas;
            $row->veteran_count = $vets;
            $row->auditor_count = $auditors;

            $passed = DB::table('person_slot')
                ->join('trainee_status', function ($j) use ($row) {
                    $j->on('trainee_status.person_id', 'person_slot.person_id');
                    $j->where('trainee_status.slot_id', $row->id);
                })
                ->where('person_slot.slot_id', $row->id)
                ->where('trainee_status.passed', 1)
                ->count();

            $row->passed = $passed;
            $row->not_passed = $ids->count() - $passed;

            $trainerCount = 0;
            foreach ($trainerPositions as $tid) {
                // Find the trainer's slot that begins within a hour of the slot start time.
                $trainerSlot = DB::table('slot')
                    ->where('position_id', $tid)
                    ->where('active', 1)
                    ->whereRaw('begins BETWEEN DATE_SUB(?, INTERVAL 1 HOUR) AND ?', [$row->begins, $row->ends])
                    ->where('description', $row->description)
                    ->value('id');

                if (!$trainerSlot) {
                    continue;
                }

                $trainers = DB::table('person_slot')->where('slot_id', $trainerSlot)->get();
                foreach ($trainers as $trainer) {
                    $trainerIds[$trainer->person_id] = true;
                }
                $trainerCount += $trainers->count();
            }

            $row->trainer_count = $trainerCount;
        }

        return [
            'slots' => $rows,
            'unique_trainers' => count(array_keys($trainerIds))
        ];
    }
}