<?php

namespace App\Lib\Reports;

use App\Models\Position;
use App\Models\Timesheet;
use App\Models\TimesheetLog;

class ForcedSigninsReport
{
    public static function execute(int $year): array
    {
        $rows = Timesheet::whereYear('on_duty', $year)
            ->where('was_signin_forced', true)
            ->with(['person:id,callsign', 'position:id,title'])
            ->get();

        if ($rows->isEmpty()) {
            return ['entries' => []];
        }

        $tsLogs = TimesheetLog::whereIntegerInRaw('timesheet_id', $rows->pluck('id'))
            ->whereRaw('json_extract(data, "$.forced") is not null')
            ->with('creator:id,callsign')
            ->get()
            ->groupBy('timesheet_id');

        $results = [];
        foreach ($rows as $row) {
            $log = $tsLogs->get($row->id);
            $untrained = null;
            if (!$log) {
                $blockers = 'unknown';
            } else {
                $log = $log[0];
                $data = $log->data;
                $forced = $data['forced'];
                $reason = $forced['reason'] ?? 'unknown';
                $blockers = Position::UNQUALIFIED_MESSAGES[$reason] ?? $reason;
                $positionId = $forced['position_id'] ?? null;
                if ($positionId) {
                    $untrained = Position::find($positionId);
                    if ($untrained) {
                        $blockers .= " ({$untrained->title})";
                    }
                }
            }

            $result = [
                'id' => $row->person_id,
                'callsign' => $row->person->callsign,
                'on_duty' => (string)$row->on_duty,
                'position_title' => $row->position->title,
                'position_id' => $row->position_id,
                'blockers' => $blockers,
                'forced_by_id' => $log?->create_person_id,
                'forced_by_callsign' => $log?->creator?->callsign,
                'signin_force_reason' => $row->signin_force_reason,
            ];

            $results[] = $result;
        }

        usort($results, function ($a, $b) {
            $cmp = strcasecmp($a['callsign'], $b['callsign']);
            if ($cmp) {
                return $cmp;
            }

            return strcmp($a['on_duty'], $b['on_duty']);
        });
        return ['entries' => $results];
    }
}