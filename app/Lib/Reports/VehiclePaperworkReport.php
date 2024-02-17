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
        $teams = DB::table('team')->where('mvr_eligible', true)->where('active', true)->orderBy('title')->get();
        $teamById = $teams->keyBy('id');
        if ($teams->isNotEmpty()) {
            $peopleByTeams = DB::table('person_team')->whereIn('team_id', $teams->pluck('id'))->get()->groupBy('person_id');
        } else {
            $peopleByTeams = collect([]);
        }

        $positions = DB::table('position')->where('mvr_eligible', true)->where('active', true)->orderBy('title')->get();
        $positionById = $positions->keyBy('id');
        if ($positions->isNotEmpty()) {
            $peopleByPositions = DB::table('person_position')->whereIn('position_id', $positions->pluck('id'))->get()->groupBy('person_id');
        } else {
            $peopleByPositions = collect([]);
        }

        $ids = $peopleByTeams->keys()->merge($peopleByPositions->keys())->unique();

        if ($ids->isNotEmpty()) {
            $eligibles = DB::table('person')->select('id', 'callsign', 'status')->whereIntegerInRaw('id', $ids)->get();
        } else {
            $eligibles =[];
        }

        $peopleById = [];

        foreach ($eligibles as $person) {
            $personTeams = $peopleByTeams->get($person->id);
            $mvrTeams = [];
            if ($personTeams) {
                foreach ($personTeams as $pt) {
                    $team = $teamById->get($pt->team_id);
                    $mvrTeams[] = [
                        'id' => $team->id,
                        'title' => $team->title,
                    ];
                }
            }

            $personPositions = $peopleByPositions->get($person->id);
            $mvrPositions = [];
            if ($personPositions) {
                foreach ($personPositions as $pp) {
                    $position = $positionById->get($pp->position_id);
                    $mvrPositions[] = [
                        'id' => $position->id,
                        'title' => $position->title,
                    ];
                }
            }

            $peopleById[$person->id] = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'mvr_teams' => $mvrTeams,
                'mvr_positions' => $mvrPositions,
            ];
        }

        $rows = DB::table('person')
            ->select(
                'id',
                'callsign',
                'status',
                DB::raw('IFNULL(person_event.signed_motorpool_agreement, false) AS signed_motorpool_agreement'),
                DB::raw('IFNULL(person_event.org_vehicle_insurance, false) AS org_vehicle_insurance'),
                DB::raw('IFNULL(person_event.mvr_eligible, false) AS mvr_eligible')
            )->join('person_event', function ($j) {
                $j->on('person_event.person_id', 'person.id');
                $j->where('person_event.year', current_year());
                $j->where(function ($q) {
                    $q->where('signed_motorpool_agreement', true);
                    $q->orWhere('org_vehicle_insurance', true);
                    $q->orWhere('mvr_eligible', true);
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
        }

        $people = array_values($peopleById);
        usort($people, fn ($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

        return $people;
     }
}