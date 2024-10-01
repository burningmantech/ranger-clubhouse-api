<?php

namespace App\Lib\Reports;

use App\Models\Timesheet;
use Carbon\Carbon;

class OnDutyReport
{
    const int DEFAULT_SHIFT_DURATION = 6 * 3600;

    public static function execute(?string $onDuty, bool $hasExcessiveDuration): array
    {
        $sql = Timesheet::with(['position:id,title', 'person:id,callsign', 'slot:id,begins,duration,description'])
            ->orderBy('on_duty');

        if (!empty($onDuty)) {
            $sql->whereYear('on_duty', Carbon::parse($onDuty)->year);
            $sql->where('on_duty', '<=', $onDuty);
            $sql->where('off_duty', '>=', $onDuty);
        } else {
            $sql->whereNull('off_duty');
        }

        $timesheets = $sql->get();
        $positions = [];
        $totalPeople = 0;

        foreach ($timesheets as $timesheet) {
            $result = [
                'id' => $timesheet->id,
                'person_id' => $timesheet->person_id,
                'callsign' => $timesheet->person->callsign,
                'on_duty' => (string)$timesheet->on_duty,
                'off_duty' => $timesheet->off_duty ? (string)$timesheet->off_duty : null,
                'duration' => $timesheet->duration,
            ];

            $slot = $timesheet->slot;
            if ($slot) {
                $result['slot'] = [
                    'id' => $slot->id,
                    'begins' => (string)$slot->begins,
                    'description' => $slot->description,
                    'duration' => $slot->duration,
                ];

                $normalDuration = $slot->duration;
            } else {
                $normalDuration = self::DEFAULT_SHIFT_DURATION;
            }

            $isExcessive = false;
            if ($timesheet->duration > ($normalDuration * 1.5)) {
                $result['excessive_duration'] = $timesheet->duration - $normalDuration;
                $result['excessive_percentage'] = (int)(($timesheet->duration / $normalDuration * 100.0) - 100);
                $isExcessive = true;
            } else if ($hasExcessiveDuration) {
                continue;
            }

            if (!isset($positions[$timesheet->position_id])) {
                $positions[$timesheet->position_id] = [
                    'id' => $timesheet->position_id,
                    'title' => $timesheet->position->title,
                    'excessive_count' => 0,
                    'timesheets' => [],
                ];
            }

            $positions[$timesheet->position_id]['timesheets'][] = $result;
            if ($isExcessive) {
                $positions[$timesheet->position_id]['excessive_count'] += 1;
            }
            $totalPeople++;
        }

        usort($positions, fn($a, $b) => strcasecmp($a['title'], $b['title']));

        return [
            'positions' => $positions,
            'on_duty' => $onDuty ?? (string)now(),
            'total_people' => $totalPeople,
            'default_excessive_duration' => self::DEFAULT_SHIFT_DURATION,
        ];
    }
}