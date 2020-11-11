<?php

namespace App\Lib\Reports;

use App\Models\PositionCredit;
use App\Models\Timesheet;

use App\Models\TimesheetMissing;

class CombinedTimesheetCorrectionRequestsReport
{
    public static function execute($year)
    {
        // Find all the unverified timesheets
        $rows = Timesheet::with(['person:id,callsign', 'position:id,title'])
            ->whereYear('on_duty', $year)
            ->where('review_status', Timesheet::STATUS_PENDING)
            ->whereNotNull('off_duty')
            ->orderBy('on_duty')
            ->get();

        // Warm up the position credit cache so the database is not being slammed.
        PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));

        $corrections = $rows->sortBy(function ($p) {
            return $p->person->callsign;
        }, SORT_NATURAL | SORT_FLAG_CASE)->values();

        $requests = [];
        foreach ($corrections as $req) {
            $requests[] = [
                'person' => $req->person,
                'position' => $req->position,
                'on_duty' => (string)$req->on_duty,
                'off_duty' => (string)$req->off_duty,
                'duration' => $req->duration,
                'credits' => $req->credits,
                'is_missing' => false,
                'notes' => $req->notes
            ];
        }

        $missing = TimesheetMissing::retrieveForPersonOrAllForYear(null, $year);

        foreach ($missing as $req) {
            $requests[] = [
                'person' => $req->person,
                'position' => $req->position,
                'on_duty' => (string)$req->on_duty,
                'off_duty' => (string)$req->off_duty,
                'duration' => $req->duration,
                'credits' => $req->credits,
                'is_missing' => true,
                'notes' => $req->notes
            ];
        }

        usort($requests, function ($a, $b) {
            return strcasecmp($a['person']->callsign, $b['person']->callsign);
        });

        return $requests;
    }
}