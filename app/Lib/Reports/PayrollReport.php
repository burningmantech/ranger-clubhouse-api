<?php

namespace App\Lib\Reports;

use App\Models\Timesheet;
use Carbon\Carbon;

class PayrollReport
{
    public static function execute(string $startTime,
                                   string $endTime,
                                   int    $breakAfterHours,
                                   int    $breakDuration,
                                   int    $hourCap,
                                   array  $positionIds): array
    {
        $secondCap = $hourCap * 3600;

        $entriesByPerson = Timesheet::select('timesheet.*')
            ->join('position', 'position.id', 'timesheet.position_id')
            ->join('person', 'person.id', 'timesheet.person_id')
            ->with([
                'person:id,callsign,first_name,last_name,email,employee_id',
                'position:id,title,paycode,no_payroll_hours_adjustment'
            ])
            ->whereNotNull('person.employee_id')
            ->whereIn('timesheet.position_id', $positionIds)
            ->where(function ($w) use ($startTime, $endTime) {
                $w->where(function ($q) use ($startTime, $endTime) {
                    $q->where('timesheet.on_duty', '<=', $startTime);
                    $q->where('timesheet.off_duty', '>=', $endTime);
                })->orWhere(function ($q) use ($startTime, $endTime) {
                    // Shift happens within the period
                    $q->where('timesheet.on_duty', '>=', $startTime);
                    $q->where('timesheet.off_duty', '<=', $endTime);
                })->orWhere(function ($q) use ($startTime, $endTime) {
                    // Shift ends within the period
                    $q->where('timesheet.off_duty', '>', $startTime);
                    $q->where('timesheet.off_duty', '<=', $endTime);
                })->orWhere(function ($q) use ($startTime, $endTime) {
                    // Shift begins within the period
                    $q->where('timesheet.on_duty', '>=', $startTime);
                    $q->where('timesheet.on_duty', '<', $endTime);
                });
            })->orderBy('timesheet.on_duty')
            ->get()
            ->groupBy('person_id');

        $results = [];

        $startTime = Carbon::parse($startTime);
        $endTime = Carbon::parse($endTime);

        $people = [];
        foreach ($entriesByPerson as $personId => $entries) {
            $shifts = [];
            foreach ($entries as $entry) {
                $notes = [];
                $onDuty = $entry->on_duty;
                $offDuty = ($entry->off_duty ?? now());

                $shift = [
                    'id' => $entry->id,
                    'position_id' => $entry->position->id,
                    'position_title' => $entry->position->title,
                    'paycode' => $entry->position->paycode,
                    'verified' => $entry->review_status == Timesheet::STATUS_VERIFIED || $entry->review_status == Timesheet::STATUS_APPROVED,
                    'orig_on_duty' => (string)$onDuty,
                    'orig_off_duty' => (string)$offDuty,
                    'orig_duration' => $offDuty->diffInSeconds($onDuty),
                ];

                if (!$entry->off_duty) {
                    $shift['still_on_duty'] = true;
                    $notes[] = 'Still on duty';
                    $offDuty = now();
                } else {
                    $offDuty = $entry->off_duty;
                }

                if ($startTime->gt($onDuty)) {
                    $notes[] = 'Split start time - original ' . self::formatDt($onDuty);
                    $onDuty = $startTime;
                }

                if ($offDuty->gt($endTime)) {
                    $notes[] = 'Split end time - original ' . self::formatDt($offDuty);
                    $offDuty = $endTime;
                }

                $duration = $offDuty->diffInSeconds($onDuty);
                if (!$entry->position->no_payroll_hours_adjustment && $secondCap && $duration > $secondCap) {
                    array_unshift($notes, 'Entry capped at ' . $hourCap . ' hours');
                    $duration = $secondCap;
                    $offDuty = $onDuty->clone()->addHours($hourCap);
                }
                $shift['duration'] = $duration;
                $shift['on_duty'] = self::formatDt($onDuty);
                $shift['off_duty'] = self::formatDt($offDuty);

                if ($entry->position->no_payroll_hours_adjustment) {
                    array_unshift($notes, 'Position set to not adjust hours.');
                } else if ($breakAfterHours) {
                    $hoursRoundedDown = (int)floor($duration / 3600);
                    if ($hoursRoundedDown > $breakAfterHours) {
                        $shift['meal_adjusted'] = self::computeMealBreak($onDuty, $duration, $breakAfterHours, $breakDuration);
                    }
                }

                $shift['notes'] = implode("\n", $notes);
                $shifts[] = $shift;
            }

            $person = $entries[0]->person;
            $people[] = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'email' => $person->email,
                'employee_id' => $person->employee_id,
                'shifts' => $shifts,
            ];
        }

        usort($people, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
        return $people;
    }

    public static function computeMealBreak(Carbon $onDuty,
                                            int    $duration,
                                            int    $breakAfterHours,
                                            int    $breakDuration): array
    {
        // Split at Lunch
        $breakForMeal = $onDuty->clone();
        $endTime = $onDuty->clone()->addSeconds($duration)->addMinutes($breakDuration);
        $breakForMeal->addHours($breakAfterHours);
        $afterMeal = $breakForMeal->clone()->addMinutes($breakDuration);

        return [
            'first_half' => [
                'on_duty' => self::formatDt($onDuty),
                'off_duty' => self::formatDt($breakForMeal),
            ],
            'second_half' => [
                'on_duty' => self::formatDt($afterMeal),
                'off_duty' => self::formatDt($endTime),
            ]
        ];
    }

    public static function formatDt(Carbon $dt): string
    {
        return $dt->format('Y-m-d G:i');
    }
}