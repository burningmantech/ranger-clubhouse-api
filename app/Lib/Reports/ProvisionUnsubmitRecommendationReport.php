<?php

namespace App\Lib\Reports;

use App\Models\AccessDocument;
use App\Models\Bmid;
use App\Models\Position;
use App\Models\Provision;
use Illuminate\Support\Facades\DB;

class ProvisionUnsubmitRecommendationReport
{
    public static function execute(): array
    {
        $year = maintenance_year();

        $rows = Provision::whereIn('provision.status', [Provision::CLAIMED, Provision::SUBMITTED])
            ->where('is_allocated', false)
            ->with('person:id,callsign,status')
            ->orderBy('provision.type')
            ->get()
            ->groupBy('person_id');

        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->keys()->toArray();

        $tickets = AccessDocument::whereIntegerInRaw('person_id', $ids)
            ->whereIn('type', [AccessDocument::STAFF_CREDENTIAL, AccessDocument::RPT])
            ->whereIn('status', [AccessDocument::CLAIMED, AccessDocument::SUBMITTED])
            ->get()
            ->keyBy('person_id');

        $bmids = DB::table('bmid')
            ->whereIntegerInRaw('person_id', $ids)
            ->where('year', $year)
            ->where('status', Bmid::SUBMITTED)
            ->get()
            ->keyBy('person_id');

        $timesheets = DB::table('timesheet')
            ->select('person_id')
            ->whereIntegerInRaw('person_id', $ids)
            ->whereYear('on_duty', $year)
            ->groupBy('person_id')
            ->get()
            ->keyBy('person_id');

        $signUps = DB::table('person_slot')
            ->select('person_slot.person_id')
            ->join('slot', 'person_slot.person_id', 'slot.id')
            ->join('position', 'position.id', 'slot.position_id')
            ->whereYear('slot.begins', $year)
            ->whereIntegerInRaw('person_slot.person_id', $ids)
            ->where('position.type', '!=', Position::TYPE_TRAINING)
            ->groupBy('person_slot.person_id')
            ->get()
            ->keyBy('person_id');

        $people = [];
        foreach ($rows as $personId => $provisions) {
            $person = $provisions[0]->person;
            $worked = $timesheets->has($person->id);

            if ($worked) {
                continue;
            }

            $result = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'provisions' => $provisions->map(fn($p) => [
                    'id' => $p->id,
                    'type' => $p->type,
                    'status' => $p->status,
                ])->values()->toArray(),
                'bmid' => $bmids->has($person->id),
                'signed_up' => $signUps->has($person->id)
            ];

            $ticket = $tickets->get($person->id);
            if ($ticket) {
                $result['ticket'] = [
                    'id' => $ticket->id,
                    'status' => $ticket->status,
                    'type' => $ticket->type
                ];
            }

            $people[] = $result;
        }

        usort($people, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
        return [
            'people' => $people,
            'year' => $year
        ];
    }
}