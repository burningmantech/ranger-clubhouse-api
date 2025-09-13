<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\Training;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SignInBlocker
{
    /**
     * Check for any blockers for a person to sign in to a given position.
     *
     * @param Person $person
     * @param Position $position
     * @param bool $checkTimes - if true check
     * @param Carbon|null $relativeTo
     * @return array
     */

    public static function check(Person $person, Position $position, bool $checkTimes = false, ?Carbon $relativeTo = null): array
    {
        $relativeTo ??= now();

        $personId = $person->id;
        $positionId = $position->id;

        $blockers = [];

        // Must do a cheetah cub shift first.
        if (($person->status == Person::INACTIVE_EXTENSION || $person->status == Person::RETIRED)
            && $position->type != Position::TYPE_TRAINING
            && $position->id != Position::CHEETAH_CUB) {
            $blockers[] = ['blocker' => Timesheet::BLOCKED_NOT_CHEETAH_CUB];
        }

        // Are they trained for this position?
        self::checkForTraining($person, $position, $blockers);

        if ($checkTimes && !$position->ignore_time_check) {
            self::checkSlots($personId, $positionId, $blockers, $relativeTo);
        }

        /**
         * A person must have an employee id if working a paid position. For international volunteers who may worked
         * but not get paid, a dummy code of "0" is fine.
         */

        // can't use empty() because "0" is treated as empty. feh.
        if ($position->paycode && is_null($person->employee_id)) {
            $blockers[] = [
                'blocker' => Timesheet::BLOCKED_NO_EMPLOYEE_ID,
            ];
        }

        // Sandman blocker - must be qualified
        if ($positionId == Position::SANDMAN) {
            if (setting('SandmanRequireAffidavit')) {
                $event = PersonEvent::findForPersonYear($person->id, current_year());
                if (!$event?->sandman_affidavit) {
                    $blockers[] = ['blocker' => Timesheet::BLOCKED_UNSIGNED_SANDMAN_AFFIDAVIT];
                }
            }

            $withinYears = setting('SandmanRequirePerimeterExpWithinYears');
            if ($withinYears) {
                if (!Timesheet::didPersonWorkPosition($person->id, $withinYears, Position::SANDMAN_QUALIFIED_POSITIONS)) {
                    $lastWorked = Timesheet::personLastWorkedAnyPosition($personId, Position::SANDMAN_QUALIFIED_POSITIONS);
                    $blockers[] = [
                        'blocker' => Timesheet::BLOCKED_NO_BURN_PERIMETER_EXP,
                        'within_years' => $withinYears,
                        'last_worked' => $lastWorked,
                    ];
                }
            }
        }

        return $blockers;
    }

    /**
     * Did the person work a shift within one hour or is still working?
     *
     * @param int $personId
     * @return bool
     */
    public static function havePreviousShift(int $personId, Carbon $relativeTo): bool
    {
        return DB::table('timesheet')
            ->where('person_id', $personId)
            ->whereYear('on_duty', current_year())
            ->where(function ($w) {
                $w->whereNull('off_duty');      // person may be changing positions.
                $w->orWhere('off_duty', '>=', now()->subHour(1));
            })->orderBy('off_duty', 'desc')
            ->exists();
    }

    /**
     * Is the person trained for a position in a given year?
     *
     * @param Person $person
     * @param Position $position
     * @param array $blockers
     */

    public static function checkForTraining(Person $person, Position $position, array &$blockers): void
    {
        $year = current_year();

        if ($position->no_training_required || $position->type == Position::TYPE_TRAINING) {
            return;
        }

        if ($position->allow_echelon && $person->status == Person::ECHELON) {
            return;
        }

        // The person has to have passed dirt training
        if (!Training::didPersonPassForYear($person, Position::TRAINING, $year)) {
            $blockers[] = [
                'blocker' => Timesheet::BLOCKED_NOT_TRAINED,
                'position' => [
                    'id' => Position::TRAINING,
                    'title' => 'In-Person Training'
                ]
            ];
        }

        $trainingId = $position->training_position_id;
        // And check if person did pass the ART
        if (!$trainingId || Training::didPersonPassForYear($person, $trainingId, $year)) {
            return;
        }

        $blockers[] = [
            'blocker' => Timesheet::BLOCKED_NOT_TRAINED,
            'position' => [
                'id' => $trainingId,
                'title' => Position::retrieveTitle($trainingId),
            ]
        ];
    }

    public static function checkSlots(int $personId, int $positionId, array &$blockers, Carbon $relativeTo): void
    {
        $timestamp = $relativeTo->timestamp;
        $year = $relativeTo->year;

        $earlyCheckIn = setting('ShiftCheckInEarlyPeriod') * 60;
        $lateCheckIn = setting('ShiftCheckInLatePeriod') * 60;

        if ($earlyCheckIn) {
            $upcoming = Slot::where('position_id', $positionId)
                ->where('active', true)
                ->where('begins_year', $year)
                ->where('begins_time', '>', $timestamp)
                ->orderBy('begins_time')
                ->first();
        } else {
            $upcoming = null;
        }

        if ($lateCheckIn) {
            $inProgress = Slot::where('position_id', $positionId)
                ->where('active', true)
                ->where('begins_year', $year)
                ->where('begins_time', '<=', $timestamp)
                ->where('ends_time', '>', $timestamp)
                ->orderBy('begins_time', 'desc')
                ->first();
        } else {
            $inProgress = null;
        }

        if (!$upcoming && !$inProgress) {
            // Might be an adhoc position with no set times.
            return;
        }

        $isEarly = ($upcoming && $timestamp < ($upcoming->begins_time - $earlyCheckIn));
        $isLate = ($inProgress && $timestamp > ($inProgress->begins_time + $lateCheckIn));

        if (!$isLate && $inProgress) {
            // Made it under the wire.
            return;
        }

        if (!$isEarly && $upcoming) {
            // Good person, you're not too early.
            return;
        }

        if (self::havePreviousShift($personId, $relativeTo)) {
            // Previous shift -- don't worry about it.
            return;
        }

        /*
         * When the person might be too late for the current shift and too early for the upcoming one,
         * report it as too early if it's 2 hours before, otherwise report it as too late.
         *
         * When the sign in is determined to be too late, check to see if a shift was worked within the
         * last hour. This handles the burn nights where people deployed on a perimeter go on a different
         * shift after the perimeter has dropped. (e.g., Burn Perimeter -> Dirt).
         */


        // Too early, and/or too late. Figure out the one to use.
        if ($isEarly && $isLate) {
            if (($timestamp - $inProgress->begins_time) < (2 * 3600)) {
                // Two hours late!
                $blockers[] = self::buildCheckInTimeBlocker(
                    Timesheet::BLOCKED_TOO_LATE, $inProgress, $timestamp, $lateCheckIn);
            } else if ($timestamp > ($upcoming->begins_time - (2 * 3600))) {
                // Two hours before is considered too EARLY!
                $blockers[] = self::buildCheckInTimeBlocker(
                    Timesheet::BLOCKED_TOO_EARLY, $upcoming, $timestamp, $earlyCheckIn);
            } else {
                $blockers[] = self::buildCheckInTimeBlocker(
                    Timesheet::BLOCKED_TOO_LATE, $inProgress, $timestamp, $lateCheckIn);
            }
        } else if ($isEarly) {
            $blockers[] = self::buildCheckInTimeBlocker(
                Timesheet::BLOCKED_TOO_EARLY, $upcoming, $timestamp, $earlyCheckIn);
        } else {
            $blockers[] = self::buildCheckInTimeBlocker(
                Timesheet::BLOCKED_TOO_LATE, $inProgress, $timestamp, $lateCheckIn);
        }
    }

    public static function buildCheckInTimeBlocker(string $blocker, Slot $slot, int $timestamp, int $cutoff): array
    {
        if ($blocker == Timesheet::BLOCKED_TOO_LATE) {
            $distance = $timestamp - $slot->begins_time;
        } else {
            $distance = $slot->begins_time - $timestamp;
        }

        $blocked = [
            'blocker' => $blocker,
            'distance' => (int)ceil($distance / 60),
            'cutoff' => (int)ceil($cutoff / 60),
            'slot' => [
                'id' => $slot->id,
                'begins' => (string)$slot->begins,
                'description' => $slot->description,
            ],
        ];

        if ($blocker == Timesheet::BLOCKED_TOO_EARLY) {
            $blocked['allowed_in'] = (int)ceil((($slot->begins_time - $cutoff) - $timestamp) / 60);
        }
        return $blocked;
    }
}
