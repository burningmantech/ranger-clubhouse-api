<?php


namespace App\Lib\Reports;


use App\Models\Slot;
use App\Models\Timesheet;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FlakeReport
{
    /**
     * Report on folks who:
     * - Signed up and worked the shift they signed up for
     * - Signed up but worked a different shift
     * - Signed up but didn't work (flake!)
     * - Worked but didn't sign up first (rogue)
     * - Worked and may have overlapped into another shift
     *
     * @param string $datetime
     * @return Collection
     */

    public static function execute(string $datetime): Collection
    {
        $positions = Slot::where('begins_year', Carbon::parse($datetime)->year)
            ->where('begins', '<=', $datetime)
            ->where('ends', '>=', $datetime)
            ->with([
                'position:id,title',
                'person_slot.person' => fn($query) => $query->select('id', 'callsign')->orderBy('callsign')
            ])
            ->orderBy('begins')
            ->get()
            ->groupBy('position_id');

        return $positions->map(function ($slots) {
            $position = $slots[0]->position;

            return [
                'id' => $position->id,
                'title' => $position->title,
                'slots' => $slots->map(function ($slot) {
                    $begins = $slot->begins;
                    $ends = $slot->ends;
                    if ($slot->person_slot->isNotEmpty()) {
                        $timesheetsByPerson = Timesheet::whereIn('person_id', $slot->person_slot->pluck('person_id'))
                            ->with('position:id,title')
                            ->whereRaw('NOT (off_duty < ? OR on_duty > ?)', [$begins->clone()->addHours(-1), $ends])
                            ->orderBy('on_duty')
                            ->get()
                            ->groupBy('person_id');
                    } else {
                        $timesheetsByPerson = null;
                    }

                    $people = $slot->person_slot->map(function ($row) use ($slot, $timesheetsByPerson) {
                        $timesheets = $timesheetsByPerson?->get($row->person_id);

                        $timesheet = $timesheets?->firstWhere('position_id', $slot->position_id);
                        if (!$timesheet) {
                            $timesheet = $timesheets[0] ?? null;
                        }

                        $person = (object)[
                            'id' => $row->person_id,
                            'callsign' => $row->person->callsign
                        ];

                        if ($timesheet) {
                            $person->timesheet = (object)[
                                'id' => $timesheet->id,
                                'position_id' => $timesheet->position_id,
                                'on_duty' => (string)$timesheet->on_duty,
                                'off_duty' => (string)$timesheet->off_duty,
                            ];

                            if ($timesheet->position_id != $slot->id) {
                                $person->timesheet->position_title = $timesheet->position->title;
                            }
                        }

                        return $person;
                    })->sortBy('callsign', SORT_NATURAL | SORT_FLAG_CASE);
                    $checkedIn = $people->filter(function ($p) use ($slot) {
                        return isset($p->timesheet) && $p->timesheet->position_id == $slot->position_id;
                    })->values();
                    $notPresent = $people->filter(function ($p) {
                        return !isset($p->timesheet);
                    })->values();
                    $differentShift = $people->filter(function ($p) use ($slot) {
                        return isset($p->timesheet) && $p->timesheet->position_id != $slot->position_id;
                    })->values();

                    // Look for rogues - those who are on shift without signing up first.
                    $rogues = Timesheet::with(['person:id,callsign'])
                        ->where('position_id', $slot->position_id)
                        ->where(function ($q) use ($begins, $ends) {
                            $q->orWhereRaw('NOT (off_duty < ? OR on_duty > ?)', [$begins->clone()->addHours(1), $ends->clone()->addHours(-1)]);
                        });

                    if (!$slot->person_slot->isEmpty()) {
                        $rogues->whereNotIn('person_id', $slot->person_slot->pluck('person_id'));
                    }

                    $rogues = $rogues->get()->map(fn($row) => [
                        'id' => $row->person_id,
                        'callsign' => $row->person?->callsign ?? 'Person #' . $row->person_id,
                        'on_duty' => (string)$row->on_duty,
                        'off_duty' => (string)$row->off_duty,
                    ])->sortBy('callsign', SORT_NATURAL | SORT_FLAG_CASE)->values();

                    return [
                        'id' => $slot->id,
                        'begins' => (string)$slot->begins,
                        'ends' => (string)$slot->ends,
                        'description' => $slot->description,
                        'signed_up' => $slot->signed_up,
                        'max' => $slot->max,
                        'checked_in' => $checkedIn,
                        'not_present' => $notPresent,
                        'different_shift' => $differentShift,
                        'rogues' => $rogues
                    ];
                })
            ];
        })->sortBy('title')->values();
    }
}