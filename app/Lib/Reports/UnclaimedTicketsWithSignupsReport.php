<?php

namespace App\Lib\Reports;

use App\Models\AccessDocument;
use Illuminate\Support\Facades\DB;

class UnclaimedTicketsWithSignupsReport
{
    public static function execute(): array
    {
        $unclaimedRows = AccessDocument::whereIn('type', AccessDocument::REGULAR_TICKET_TYPES)
            ->where('status', AccessDocument::QUALIFIED)
            ->with('person:id,callsign,status,email')
            ->get();

        if ($unclaimedRows->isEmpty()) {
            return [];
        }

        $peopleIds = $unclaimedRows->pluck('person_id')->unique()->values();

        $year = current_year();

        $signUps = DB::table('slot')
            ->select('person_slot.person_id')
            ->join('person_slot', 'slot.id', 'person_slot.slot_id')
            ->where('begins_year', current_year())
            ->where('begins', '>=', "$year-08-15")
            ->whereIntegerInRaw('person_slot.person_id', $peopleIds)
            ->groupBy('person_slot.person_id')
            ->get()
            ->keyBy('person_id');

        $tickets = [];
        foreach ($unclaimedRows as $unclaimed) {
            $person = $unclaimed->person;
            $tickets[] = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'email' => $person->email,
                'access_document_id' => $unclaimed->id,
                'expiry_date' => $unclaimed->expiry_date->format('Y-m-d'),
                'type' => $unclaimed->type,
                'has_signup' => $signUps->has($person->id),
            ];
        }

        usort($tickets, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

        return $tickets;
    }
}