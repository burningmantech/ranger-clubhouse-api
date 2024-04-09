<?php

namespace App\Lib;

use App\Models\ActionLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftDropReport
{
    public static function execute(array $positionIds, int $year, int $hours): array
    {
        $slots = DB::table('slot')
            ->select('slot.*', 'position.title')
            ->join('position', 'position.id', 'slot.position_id')
            ->where('slot.active', true)
            ->where('begins_year', $year)
            ->whereIn('position_id', $positionIds)
            ->orderBy('position_id')
            ->orderBy('begins')
            ->get();

        $results = [];
        foreach ($slots as $slot) {
            $begins = Carbon::parse($slot->begins);
            $dropped = ActionLog::where('event', 'person-slot-remove')
                ->whereYear('created_at', $year)
                ->whereRaw('created_at BETWEEN ? AND ?', [(string)$begins->subHours($hours), $slot->begins])
                ->whereRaw("json_extract(data, '$.slot_id')={$slot->id}")
                ->with('target_person:id,callsign')
                ->get();

            if ($dropped->isEmpty()) {
                continue;
            }

            foreach ($dropped as $row) {
                $results[] = [
                    'position_id' => $slot->position_id,
                    'position_title' => $slot->title,
                    'description' => $slot->description,
                    'begins' => $slot->begins,
                    'person_id' => $row->target_person_id,
                    'callsign' => $row->target_person->callsign,
                    'dropped_at' => (string)$row->created_at,
                    'hours_before' => round(Carbon::parse($row->created_at)->diffInMinutes($slot->begins) / 60.0, 1),
                ];
            }
        }

        return [ 'people' => $results ];
    }
}