<?php

namespace App\Lib\Reports;

use App\Models\AccessDocument;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class ClaimedTicketsWithNoSignups
{
    public static function execute(): array
    {
        $claimedRows = AccessDocument::whereIn('type', [AccessDocument::RPT, AccessDocument::STAFF_CREDENTIAL])
            ->whereIn('status', [AccessDocument::CLAIMED, AccessDocument::SUBMITTED])
            ->with('person:id,callsign,status,first_name,last_name,email')
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
            ->whereYear('begins', current_year())
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

        $otPeople = DB::table('person_online_training')
            ->whereIntegerInRaw('person_id', $peopleIds)
            ->get()
            ->keyBy('person_id');

        $tickets = [];
        foreach ($claimedRows as $claimed) {
            $person = $claimed->person;
            $personId = $person->id;
            $otCompleted = $otPeople->has($personId);
            $hasSignups = $signUps->has($personId);
            $didWork = $timesheets->has($personId);

            if ($otCompleted && $didWork) {
                // Person is cool, OT was completed and did work on playa.
                continue;
            }

            $tickets[] = [
                'id' => $personId,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'email' => $person->email,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'access_document_id' => $claimed->id,
                'type' => $claimed->type,
                'ot_completed' => $otCompleted,
                'has_signups' => $hasSignups,
                'did_work' => $didWork,
            ];
        }

        usort($tickets, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
        return $tickets;
    }
}