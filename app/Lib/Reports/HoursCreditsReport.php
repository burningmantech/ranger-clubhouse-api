<?php

namespace App\Lib\Reports;

use App\Lib\WorkSummary;

use App\Models\EventDate;
use App\Models\Person;
use App\Models\PositionCredit;
use App\Models\Timesheet;

class HoursCreditsReport
{
    /**
     * Report on hours and credits for the given year
     *
     * @param int $year
     * @return array
     */
    public static function execute(int $year)
    {
        $eventDates = EventDate::findForYear($year);

        if (!$eventDates) {
            return [
                'event_start' => '',
                'event_end' => '',
                'people' => []
            ];
        }

        $people = Person::whereNotIn('status', [
            Person::ALPHA,
            Person::AUDITOR,
            Person::BONKED,
            Person::PAST_PROSPECTIVE,
            Person::PROSPECTIVE,
            Person::UBERBONKED
        ])->whereRaw('EXISTS (SELECT 1 FROM timesheet WHERE timesheet.person_id=person.id AND YEAR(on_duty)=? LIMIT 1)', [$year])
            ->orderBy('callsign')
            ->get();

        if ($people->isEmpty()) {
            return [
                'event_start' => (string)$eventDates->event_start,
                'event_end' => (string)$eventDates->event_end,
                'people' => []
            ];
        }


        $personIds = $people->pluck('id');
        $yearsByIds = Timesheet::yearsRangeredCountForIds($personIds);

        PositionCredit::warmYearCache($year, []);

        $entriesByPerson = Timesheet::whereIntegerInRaw('person_id', $personIds)
            ->whereYear('on_duty', $year)
            ->with(['position:id,count_hours'])
            ->get()
            ->groupBy('person_id');

        $results = [];
        $now = now()->timestamp;

        foreach ($people as $person) {
            $entries = $entriesByPerson[$person->id] ?? null;
            if (!$entries) {
                continue;
            }

            $summary = new WorkSummary($eventDates->event_start->timestamp, $eventDates->event_end->timestamp, $year);
            foreach ($entries as $entry) {
                $summary->computeTotals(
                    $entry->position_id,
                    $entry->on_duty->timestamp,
                    $entry->off_duty ? $entry->off_duty->timestamp : $now,
                    $entry->position->count_hours
                );
            }

            $results[] = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'first_name' => $person->desired_first_name(),
                'last_name' => $person->last_name,
                'email' => $person->email,
                'years' => $yearsByIds[$person->id] ?? 0,
                'pre_event_duration' => $summary->pre_event_duration,
                'pre_event_credits' => $summary->pre_event_credits,
                'event_duration' => $summary->event_duration,
                'event_credits' => $summary->event_credits,
                'post_event_duration' => $summary->post_event_duration,
                'post_event_credits' => $summary->post_event_credits,
                'total_duration' => ($summary->pre_event_duration + $summary->event_duration + $summary->post_event_duration + $summary->other_duration),
                'total_credits' => ($summary->pre_event_credits + $summary->event_credits + $summary->post_event_credits),
                'other_duration' => $summary->other_duration,
                'counted_duration' => ($summary->pre_event_duration + $summary->event_duration + $summary->post_event_duration),
            ];
        }

        return [
            'event_start' => (string)$eventDates->event_start,
            'event_end' => (string)$eventDates->event_end,
            'people' => $results
        ];
    }
}