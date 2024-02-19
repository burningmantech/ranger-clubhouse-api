<?php

namespace App\Lib\Reports;

use App\Models\Position;
use App\Models\Team;
use App\Models\TrainerStatus;
use Illuminate\Support\Facades\DB;

class TeamMembershipReport
{
    public static function execute(Team $team, bool $excludePublicOnly = true): array
    {
        $positions = $team->positions()->orderBy('title')->get();

        $roster = DB::table('person_team')
            ->select('person.id', 'person.callsign', 'person.status')
            ->join('person', 'person.id', 'person_team.person_id')
            ->where('team_id', $team->id)
            ->get();

        $peopleIds = [];

        foreach ($roster as $member) {
            self::buildPerson($member, $peopleIds);
            $peopleIds[$member->id]['is_member'] = true;
        }

        self::retrievePositionGrants($positions, Position::TEAM_CATEGORY_ALL_MEMBERS, $peopleIds);
        self::retrievePositionGrants($positions, Position::TEAM_CATEGORY_OPTIONAL, $peopleIds);
        self::retrievePositionGrants($positions, Position::TEAM_CATEGORY_PUBLIC, $peopleIds, $excludePublicOnly);

        $people = array_values($peopleIds);
        usort($people, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

        return [
            'people' => $people,
            'positions' => $positions->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'active' => $p->active,
                'team_category' => $p->team_category
            ])->values(),
        ];
    }

    public static function buildPerson($person, array &$peopleIds): void
    {
        if (isset($peopleIds[$person->id])) {
            return;
        }
        $peopleIds[$person->id] = [
            'id' => $person->id,
            'callsign' => $person->callsign,
            'status' => $person->status,
            'positions' => []
        ];
    }

    public static function retrievePositionGrants($positions,
                                                  string $category,
                                                  array &$peopleIds,
                                                  bool $excludePublicOnly = false): void
    {
        foreach ($positions->filter(fn($p) => $p->team_category == $category) as $position) {
            $grants = DB::table('person_position')
                ->select('person.id', 'person.callsign', 'person.status')
                ->join('person', 'person.id', 'person_position.person_id')
                ->where('person_position.position_id', $position->id)
                ->get();

            if ($grants->isEmpty()) {
                continue;
            }

            $personIds = $grants->pluck('id');
            $lastWorked = DB::table('timesheet')
                ->select('person_id', DB::raw('MAX(on_duty) as on_duty'))
                ->whereIntegerInRaw('person_id', $personIds)
                ->where('position_id', $position->id)
                ->groupBy('person_id')
                ->get()
                ->keyBy('person_id');

            if ($position->type == Position::TYPE_TRAINING) {
                // Is this a trainer's position?
                $isTrainer = false;
                foreach (Position::TRAINERS as $pos => $trainers) {
                    if (in_array($position->id, $trainers)) {
                        $isTrainer = true;
                        break;
                    }
                }

                if ($isTrainer) {
                    $lastTaught = DB::table('slot')
                        ->select('person_slot.person_id', DB::raw('MAX(slot.begins) as begins'))
                        ->join('person_slot', 'person_slot.slot_id', 'slot.id')
                        ->join('trainer_status', function ($j) {
                            $j->on('trainer_status.trainer_slot_id', 'slot.id');
                            $j->whereColumn('trainer_status.person_id', 'person_slot.person_id');
                        })->where('slot.position_id', $position->id)
                        ->whereIntegerInRaw('person_slot.person_id', $personIds)
                        ->where('trainer_status.status', TrainerStatus::ATTENDED)
                        ->groupBy('person_slot.person_id')
                        ->get()
                        ->keyBy('person_id');
                } else {
                    $isTraining = true;
                    $lastTrained = DB::table('slot')
                        ->select('person_slot.person_id', DB::raw('MAX(slot.begins) as begins'))
                        ->join('person_slot', 'person_slot.slot_id', 'slot.id')
                        ->join('trainee_status', function ($j) {
                            $j->on('trainee_status.slot_id', 'slot.id');
                            $j->whereColumn('trainee_status.person_id', 'person_slot.person_id');
                        })->where('slot.position_id', $position->id)
                        ->whereIntegerInRaw('person_slot.person_id', $personIds)
                        ->where('trainee_status.passed', true)
                        ->groupBy('person_slot.person_id')
                        ->get()
                        ->keyBy('person_id');
                }
            } else {
                $isTrainer = false;
                $isTraining = false;
            }

            foreach ($grants as $person) {
                if ($excludePublicOnly && !isset($peopleIds[$person->id])) {
                    continue;
                }
                self::buildPerson($person, $peopleIds);
                $pInfo = [
                    'id' => $position->id,
                ];
                if ($isTrainer) {
                    $pInfo['is_trainer'] = true;
                    $worked = $lastTaught->get($person->id)?->begins;
                } else if ($isTraining) {
                    $pInfo['is_training'] = true;
                    $worked = $lastTrained->get($person->id)?->begins;
                } else {
                    $worked = $lastWorked->get($person->id)?->on_duty;
                }
                $pInfo['worked_on'] = $worked;
                $peopleIds[$person->id]['positions'][] = $pInfo;
            }
        }
    }
}