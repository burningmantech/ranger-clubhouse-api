<?php

namespace App\Lib\Reports;

use App\Lib\WorkSummary;

use App\Models\EventDate;
use App\Models\PositionCredit;
use App\Models\Timesheet;

class TimesheetWorkSummaryReport
{
    /**
     * Summarize a person's timesheet into pre-event, event week, and post event hours and credits.
     *
     * @param int $personId
     * @param int $year
     * @return object
     */

    public static function execute(int $personId, int $year) : object
    {
        $rows = Timesheet::findForQuery(['person_id' => $personId, 'year' => $year]);

        $eventDates = EventDate::findForYear($year);

        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        if (!$eventDates) {
            // No event dates - return everything as happening during the event
            $time = $rows->pluck('duration')->sum();
            $credits = $rows->pluck('credits')->sum();

            return (object) [
                'pre_event_duration' => 0,
                'pre_event_credits' => 0,
                'event_duration' => $time,
                'event_credits' => $credits,
                'post_event_duration' => 0,
                'post_event_credits' => 0,
                'other_duration' => 0,
                'counted_duration' => 0,
                'total_duration' => $time,
                'total_credits' => $credits,
                'no_event_dates' => true,
            ];
        }

        $summary = new WorkSummary($eventDates->event_start->timestamp, $eventDates->event_end->timestamp, $year);

        foreach ($rows as $row) {
            $summary->computeTotals(
                $row->position_id,
                $row->on_duty->timestamp,
                ($row->off_duty ?? now())->timestamp,
                $row->position->count_hours
            );
        }

        return (object) [
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
            'event_start' => (string)$eventDates->event_start,
            'event_end' => (string)$eventDates->event_end,
         ];
    }
}