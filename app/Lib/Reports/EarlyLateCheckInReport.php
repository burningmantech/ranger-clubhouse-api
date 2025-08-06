<?php

namespace App\Lib\Reports;

use App\Models\Slot;
use App\Models\Timesheet;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class EarlyLateCheckInReport
{
    public int $earlyCheckIn;
    public int $lateCheckIn;
    public Collection $slotsByPositionAsc;
    public Collection $slotsByPositionDesc;
    public array $entries = [];
    public array $positions = [];
    public array $people = [];
    public Collection $timesheets;
    public Collection $timesheetsByPerson;

    public function __construct(public int $year)
    {
        $this->earlyCheckIn = setting('ShiftCheckInEarlyPeriod') * 60;
        $this->lateCheckIn = setting('ShiftCheckInLatePeriod') * 60;
        $slots = Slot::where('begins_year', $this->year)
            ->select('slot.*')
            ->join('position', 'position.id', '=', 'slot.position_id')
            ->where('slot.active', true)
            ->where('position.not_timesheet_eligible', false)
            ->orderBy('slot.begins_time')
            ->get();
        $this->slotsByPositionAsc = $slots->groupBy('position_id');
        $this->slotsByPositionDesc = $slots->sortByDesc('begins_time')->values()->groupBy('position_id');
        $this->timesheets = Timesheet::whereYear('on_duty', $year)
            ->with(['person:id,callsign,status', 'position:id,title', 'created_log'])
            ->orderBy('on_duty')
            ->get();
        $this->timesheetsByPerson = $this->timesheets->groupBy('person_id');
    }

    public static function execute(int $year): array
    {
        $report = new  EarlyLateCheckInReport($year);

        foreach ($report->timesheets as $entry) {
            if ($report->slotsByPositionAsc->get($entry->position_id)) {
                $report->checkTimes($entry);
            }
        }

        usort($report->entries, fn($a, $b) => strcmp($a['timesheet']['on_duty'], $b['timesheet']['on_duty']));
        $people = array_values($report->people);
        usort($people, fn($a, $b) => $b['total'] - $a['total']);
        $positions = array_values($report->positions);
        usort($positions, fn($a, $b) => strcasecmp($a['title'], $b['title']));

        foreach ($people as &$person) {
            $person['positions'] = array_values($person['positions']);
            usort($person['positions'], fn($a, $b) => $b['total'] - $a['total']);
        }

        return [
            'early_check_in' => $report->earlyCheckIn,
            'late_check_in' => $report->lateCheckIn,
            'entries' => $report->entries,
            'people' => $people,
            'positions' => $positions,
        ];
    }

    public function checkTimes(Timesheet $entry): void
    {
        $personId = $entry->person_id;
        $onDuty = $entry->on_duty;
        $positionId = $entry->position_id;
        $timestamp = $onDuty->timestamp;
        $earlyCheckIn = $this->earlyCheckIn;
        $lateCheckIn = $this->lateCheckIn;

        $upcoming = null;
        if ($earlyCheckIn) {
            $slots = $this->slotsByPositionAsc->get($positionId);
            foreach ($slots as $slot) {
                if ($slot->begins_time > $timestamp) {
                    $upcoming = $slot;
                    break;
                }
            }
        }

        $inProgress = null;
        if ($lateCheckIn) {
            $slots = $this->slotsByPositionDesc->get($positionId);
            foreach ($slots as $slot) {
                if ($slot->begins_time <= $timestamp && $slot->ends_time >= $timestamp) {
                    $inProgress = $slot;
                    break;
                }
            }
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

        if (self::havePreviousShift($personId, $onDuty)) {
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
                $this->buildOutOfBounds('late', $entry, $inProgress, $timestamp, $lateCheckIn);
            } else if ($timestamp > ($upcoming->begins_time - (2 * 3600))) {
                // Two hours before is considered too EARLY!
                $this->buildOutOfBounds('early', $entry, $upcoming, $timestamp, $earlyCheckIn);
            } else {
                $this->buildOutOfBounds('late', $entry, $inProgress, $timestamp, $lateCheckIn);
            }
        } else if ($isEarly) {
            $this->buildOutOfBounds('early', $entry, $upcoming, $timestamp, $earlyCheckIn);
        } else {
            $this->buildOutOfBounds('late', $entry, $inProgress, $timestamp, $lateCheckIn);
        }
    }

    public function buildOutOfBounds(string $type, Timesheet $entry, Slot $slot, int $timestamp, int $cutoff): void
    {
        if ($type == 'late') {
            $distance = $timestamp - $slot->begins_time;
        } else {
            $distance = $slot->begins_time - $timestamp;
        }

        $result = [
            'type' => $type,
            'distance' => (int)ceil($distance / 60),
            'position' => [
                'id' => $entry->position_id,
                'title' => $entry->position->title,
            ],
            'timesheet' => [
                'id' => $entry->id,
                'on_duty' => (string)$entry->on_duty,
                'via' => $entry->createdVia(),
            ],
            'person' => [
                'id' => $entry->person_id,
                'callsign' => $entry->person->callsign,
            ],
            'slot' => [
                'id' => $slot->id,
                'begins' => (string)$slot->begins,
                'description' => $slot->description,
            ],
        ];

        if (!isset($this->people[$entry->person_id])) {
            $this->people[$entry->person_id] = [
                'id' => $entry->person_id,
                'callsign' => $entry->person->callsign,
                'total' => 0,
                'positions' => [],
            ];
        }

        if (!isset($this->people[$entry->person_id]['positions'][$entry->position_id])) {
            $this->people[$entry->person_id]['positions'][$entry->position_id] = [
                'id' => $entry->position_id,
                'title' => $entry->position->title,
                'total' => 0,
            ];
        }

        if (!isset($this->positions[$entry->position_id])) {
            $this->positions[$entry->position_id] = [
                'id' => $entry->position_id,
                'title' => $entry->position->title,
                'total' => 0,
            ];
        }

        $this->people[$entry->person_id]['positions'][$entry->position_id]['total'] += 1;
        $this->people[$entry->person_id]['total'] += 1;
        $this->positions[$entry->position_id]['total'] += 1;

        if ($type == 'early') {
            $result['allowed_in'] = (int)ceil((($slot->begins_time - $cutoff) - $timestamp) / 60);
        }

        $this->entries[] = $result;
    }

    /**
     * Did the person work a shift within one hour or is still working?
     *
     * @param int $personId
     * @param Carbon $onDuty
     * @return bool
     */

    public function havePreviousShift(int $personId, Carbon $onDuty): bool
    {
        $timesheets = $this->timesheetsByPerson->get($personId);

        $hourBefore = $onDuty->clone()->subHour();
        foreach ($timesheets as $t) {
            if ($t->off_duty?->gte($hourBefore) && $t->off_duty?->lte($onDuty)) {
                return true;
            }
        }
        return false;
    }
}