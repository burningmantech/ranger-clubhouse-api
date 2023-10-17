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
                $reason = 'unknown';
            } else {
                $log = $log[0];
                $data = json_decode($log->data);
                $forced = $data->forced;
                $reason = Position::UNQUALIFIED_MESSAGES[$forced->reason] ?? $forced->reason;
                if ($forced->position_id ?? null) {
                    $untrained = Position::find($forced->position_id);
                    if ($untrained) {
                        $reason .= " ({$untrained->title})";
                    }
                }
            }

            $result = [
                'id' => $row->person_id,
                'callsign' => $row->person->callsign,
                'on_duty' => (string)$row->on_duty,
                'position_title' => $row->position->title,
                'position_id' => $row->position_id,
                'reason' => $reason,
                'forced_by_id' => $log->create_person_id,
                'forced_by_callsign' => $log->creator->callsign,
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