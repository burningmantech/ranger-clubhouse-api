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
            $positionId = null;

            if (!$log) {
                $blockers = 'unknown';
            } else {
                $log = $log[0];
                $data = $log->data;
                if (is_bool($data['forced'])) {
                    // New blocker format
                    $blockers = $data['blockers'];
                } else {
                    // Old blocker format -- convert to new.
                    $forced = $data['forced'];
                    $reason = $forced['reason'] ?? 'unknown';
                    $blocker = [
                        'blocker' => Timesheet::OLD_BLOCKERS[$reason] ?? 'unknown',
                    ];

                    if ($reason == Timesheet::BLOCKED_NO_BURN_PERIMETER_EXP) {
                        $blocker['within_years'] = 5;
                    }

                    $positionId = $forced['position_id'] ?? null;
                    if ($positionId) {
                        $blocker['position'] = [
                            'id' => $positionId,
                            'title' => Position::retrieveTitle($positionId)
                        ];
                    }

                    $blockers = [$blocker];
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

        usort($results, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']) ?: strcmp($a['on_duty'], $b['on_duty']));
        return ['entries' => $results];
    }
}