<?php

namespace App\Lib\Reports;

use Illuminate\Support\Facades\DB;

class VehiclePaperworkReport
{

    /**
     * Report on people who either have signed the motorpool agreement, and/or have org insurance.
     *
     * @return array
     */

    public static function execute(): array
    {
        $teams = DB::table('team')
            ->where(function ($w) {
                $w->where('mvr_eligible',true);
                $w->orWhere('pvr_eligible', true);
            })->where('active', true)
            ->orderBy('title')
            ->get();

        $teamById = $teams->keyBy('id');
        if ($teams->isNotEmpty()) {
            $peopleByTeams = DB::table('person_team')->whereIn('team_id', $teams->pluck('id'))->get()->groupBy('person_id');
        } else {
            $peopleByTeams = collect([]);
        }

        $positions = DB::table('position')
            ->where(function ($w) {
                $w->where('mvr_eligible',true);
                $w->orWhere('pvr_eligible', true);
            })->where('active', true)
            ->orderBy('title')
            ->get();

        $positionById = $positions->keyBy('id');
        if ($positions->isNotEmpty()) {
            $peopleByPositions = DB::table('slot')
                ->select('person_slot.person_id', 'slot.position_id')
                ->join('person_slot', 'person_slot.slot_id', 'slot.id')
                ->where('slot.begins_year', current_year())
                ->whereIn('slot.position_id', $positions->pluck('id'))
                ->where('slot.active', true)
                ->get()
                ->groupBy('person_id');
        } else {
            $peopleByPositions = collect([]);
        }

        $ids = $peopleByTeams->keys()->merge($peopleByPositions->keys())->unique();

        if ($ids->isNotEmpty()) {
            $eligibles = DB::table('person')->select('id', 'callsign', 'status')->whereIntegerInRaw('id', $ids)->get();
        } else {
            $eligibles = [];
        }

        $peopleById = [];

        foreach ($eligibles as $person) {
            $personTeams = $peopleByTeams->get($person->id);
            $mvrTeams = [];
            $pvrTeams = [];
            if ($personTeams) {
                foreach ($personTeams as $pt) {
                    $team = $teamById->get($pt->team_id);
                    $teamInfo = [
                        'id' => $team->id,
                        'title' => $team->title,

                    ];
                    if ($team->mvr_eligible) {
                        $mvrTeams[] = $teamInfo;
                    } else {
                        $pvrTeams[] = $teamInfo;
                    }
                }
                usort($mvrTeams, fn($a, $b) => strcasecmp($a['title'], $b['title']));
                usort($pvrTeams, fn($a, $b) => strcasecmp($a['title'], $b['title']));
            }

            $personPositions = $peopleByPositions->get($person->id);
            $mvrPositions = [];
            $pvrPositions = [];
            if ($personPositions) {
                foreach ($personPositions as $pp) {
                    $position = $positionById->get($pp->position_id);
                    $pInfo = [
                        'id' => $position->id,
                        'title' => $position->title,
                    ];
                    if ($position->mvr_eligible) {
                        $mvrPositions[] = $pInfo;
                    } else {
                        $pvrPositions[] = $pInfo;
                    }
                }
                usort($mvrPositions, fn($a, $b) => strcasecmp($a['title'], $b['title']));
                usort($pvrPositions, fn($a, $b) => strcasecmp($a['title'], $b['title']));
            }

            $peopleById[$person->id] = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'mvr_teams' => $mvrTeams,
                'mvr_positions' => $mvrPositions,
                'pvr_teams' => $pvrTeams,
                'pvr_positions' => $pvrPositions,
            ];
        }

        $rows = DB::table('person')
            ->select(
                'person.id',
                'person.callsign',
                'person.status',
                DB::raw('IFNULL(person_event.signed_motorpool_agreement, false) AS signed_motorpool_agreement'),
                DB::raw('IFNULL(person_event.org_vehicle_insurance, false) AS org_vehicle_insurance'),
                DB::raw('IFNULL(person_event.mvr_eligible, false) AS mvr_eligible'),
                DB::raw('IFNULL(person_event.may_request_stickers, false) AS pvr_eligible')
            )->join('person_event', function ($j) {
                $j->on('person_event.person_id', 'person.id');
                $j->where('person_event.year', current_year());
                $j->where(function ($q) {
                    $q->where('signed_motorpool_agreement', true);
                    $q->orWhere('org_vehicle_insurance', true);
                    $q->orWhere('mvr_eligible', true);
                    $q->orWhere('may_request_stickers', true);
                });
            })
            ->get();

        foreach ($rows as $row) {
            if (!isset($peopleById[$row->id])) {
                $peopleById[$row->id] = [
                    'id' => $row->id,
                    'callsign' => $row->callsign,
                    'status' => $row->status,
                ];
            }
            $person = &$peopleById[$row->id];
            $person['signed_motorpool_agreement'] = $row->signed_motorpool_agreement;
            $person['org_vehicle_insurance'] = $row->org_vehicle_insurance;
            $person['mvr_eligible'] = $row->mvr_eligible;
            $person['pvr_eligible'] = $row->pvr_eligible;
        }

        $people = array_values($peopleById);
        usort($people, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

        return $people;
    }
}