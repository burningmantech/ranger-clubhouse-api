<?php

namespace App\Lib\Reports;

use App\Models\Timesheet;
use Carbon\Carbon;

class PayrollReport
{
    public static function execute(string $startTime,
                                   string $endTime,
                                   int $breakDuration,
                                   int $hourCap, array $positionIds): array
    {
        $secondCap = $hourCap * 3600;

        $entriesByPerson = Timesheet::select('timesheet.*')
            ->join('position', 'position.id', 'timesheet.position_id')
            ->with([
                'person:id,callsign,first_name,last_name,email,employee_id',
                'position:id,title,paycode,no_payroll_hours_adjustment'
            ])
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
                    'orig_on_duty' => (string) $onDuty,
                    'orig_off_duty' => (string) $offDuty,
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
                    array_unshift($notes, 'Entry capped at '.$hourCap.' hours');
                    $duration = $secondCap;
                    $offDuty = $onDuty->clone()->addHours($hourCap);
                }
                $shift['duration'] = $duration;
                $shift['on_duty'] = self::formatDt($onDuty);
                $shift['off_duty'] = self::formatDt($offDuty);

                $startHour = $onDuty->hour;
                if ($entry->position->no_payroll_hours_adjustment) {
                    array_unshift($notes, 'Position set to not adjust hours.');
                } else if ($duration >= (6 * 3600)) {
                    if ($startHour >= 6 && $startHour <= 10) {
                        $shift['meal_adjusted'] = self::computeMealBreak('lunch', $onDuty, $duration, 12, 0, $breakDuration);
                    } else if ($startHour >= 12 && $startHour < 15) {
                        $shift['meal_adjusted'] = self::computeMealBreak('dinner', $onDuty, $duration, 17, 0, $breakDuration);
                    } else {
                        array_unshift($notes, 'No break - start not between 06:00 & 10:00, or 12:00 & 15:00');
                    }
                } else {
                    array_unshift($notes, 'No break - duration < 6 hours');
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

    public static function computeMealBreak(string $meal,
                                            Carbon $onDuty, int $duration,
                                            int    $startBreakHour, int $startBreakMin,
                                            int    $breakDuration): array
    {
        // Split at Lunch
        $breakForMeal = $onDuty->clone();
        $breakForMeal->setTime($startBreakHour, $startBreakMin);
        $remaining = $breakForMeal->diffInSeconds($onDuty);
        $afterMeal = $breakForMeal->clone()->addMinutes($breakDuration);
        $endTime = $afterMeal->clone()->addSeconds($duration - $remaining);

        return [
            'meal' => $meal,
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