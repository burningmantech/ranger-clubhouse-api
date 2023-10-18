<?php

namespace App\Lib\Reports;

use App\Models\Position;
use App\Models\Timesheet;
use App\Models\TimesheetMissing;
use Illuminate\Support\Facades\DB;

class TimesheetCorrectionsStatisticsReport
{
    public static function execute(int $year): array
    {
        $reviewerById = [];

        $missingStatuses = DB::table('timesheet_missing')
            ->select('review_status', DB::raw('count(review_status) as total'))
            ->whereYear('on_duty', $year)
            ->whereIn('review_status', [TimesheetMissing::APPROVED, TimesheetMissing::REJECTED])
            ->whereColumn('reviewer_person_id', '!=', 'person_id')
            ->groupBy('review_status')
            ->get()
            ->keyBy('review_status');

        $missingReviewers = DB::table('timesheet_missing')
            ->select('reviewer_person_id', 'review_status', DB::raw('count(review_status) as total'))
            ->whereYear('on_duty', $year)
            ->whereIn('review_status', [TimesheetMissing::APPROVED, TimesheetMissing::REJECTED])
            ->whereColumn('reviewer_person_id', '!=', 'person_id')
            ->groupBy('reviewer_person_id', 'review_status')
            ->get();

        foreach ($missingReviewers as $reviewer) {
            self::setupReviewer($reviewerById, $reviewer->reviewer_person_id);
            $reviewerById[$reviewer->reviewer_person_id]['missing_' . $reviewer->review_status] += $reviewer->total;
        }

        $topMissing = DB::table('timesheet_missing')
            ->select(
                'position_id',
                DB::raw('count(position_id) as total'),
                DB::raw('(SELECT title FROM position WHERE position.id=position_id LIMIT 1) as title')
            )->whereYear('on_duty', $year)
            ->whereColumn('reviewer_person_id', '!=', 'person_id')
            ->groupBy('position_id')
            ->orderBy('total', 'desc')
            ->limit(20)
            ->get();

        $topMissingSubmitters = DB::table('timesheet_missing')
            ->select(
                'person_id',
                DB::raw('count(person_id) as total'),
            )->whereYear('on_duty', $year)
            ->whereColumn('reviewer_person_id', '!=', 'person_id')
            ->groupBy('person_id')
            ->orderBy('total', 'desc')
            ->limit(20)
            ->get();

        $peopleById = DB::table('person')
            ->select('id', 'callsign')
            ->whereIntegerInRaw('id', $topMissingSubmitters->pluck('person_id'))
            ->get()
            ->keyBy('id');

        $topMissingPeople = [];
        foreach ($topMissingSubmitters as $submitter) {
            $id = $submitter->person_id;
            $topMissingPeople[] = [
                'id' => $id,
                'callsign' => $peopleById[$submitter->person_id]->callsign ?? "#$id",
                'total' => $submitter->total
            ];
        }

        $deletedCount = DB::table('timesheet_log')
            ->leftJoin('timesheet', 'timesheet.id', 'timesheet_log.timesheet_id')
            ->whereYear('created_at', $year)
            ->whereNotNull('timesheet_id')
            ->whereNull('timesheet.id')
            ->distinct('timesheet_id')
            ->count('timesheet_id');

        $logs = DB::table('timesheet_log')
            ->select(
                DB::raw('json_value(timesheet_log.data, "$.review_status[0]") as old_status'),
                DB::raw('json_value(timesheet_log.data, "$.review_status[1]") as new_status'),
                'timesheet.position_id',
                'timesheet.person_id',
                'timesheet_log.person_id',
                'timesheet_log.create_person_id',
                'timesheet_log.timesheet_id',
            )->join('timesheet', 'timesheet.id', 'timesheet_log.timesheet_id')
            ->whereYear('timesheet_log.created_at', $year)
            ->whereNotNull('timesheet_id')
            ->whereRaw('json_extract(timesheet_log.data, "$.review_status") is not null')
            ->get();

        $rejected = 0;
        $approved = 0;
        $topCorrectedCounts = [];
        $topCorrectedSubmitters = [];

        foreach ($logs as $log) {
            if ($log->person_id == $log->create_person_id) {
                // Don't bother with people reviewing themselves.
                continue;
            }

            $status = $log->new_status;
            if ($status != Timesheet::STATUS_APPROVED && $status != Timesheet::STATUS_REJECTED) {
                continue;
            }

            $topCorrectedCounts[$log->position_id] ??= 1;
            $topCorrectedCounts[$log->position_id] += 1;
            $topCorrectedSubmitters[$log->person_id] ??= 1;
            $topCorrectedSubmitters[$log->person_id] += 1;

            if (!isset($reviewerById[$log->create_person_id])) {
                self::setupReviewer($reviewerById, $log->create_person_id);
            }

            if ($status == Timesheet::STATUS_REJECTED) {
                $reviewerById[$log->create_person_id]['corrections_rejected'] += 1;
                $rejected++;
            } else {
                $reviewerById[$log->create_person_id]['corrections_approved'] += 1;
                $approved++;
            }
        }

        if (empty($reviewerById)) {
            $people = [];
        } else {
            $people = DB::table('person')
                ->select('id', 'callsign')
                ->whereIntegerInRaw('id', array_keys($reviewerById))
                ->orderBy('callsign')
                ->get();
        }

        arsort($topCorrectedCounts);
        $topCorrectedCounts = array_slice($topCorrectedCounts, 0, 20, true);

        if (!empty($topCorrectedCounts)) {
            $positions = Position::whereIn('id', array_keys($topCorrectedCounts))->get()->keyBy('id');
        } else {
            $positions = [];
        }

        $topCorrectedPositions = [];
        foreach ($topCorrectedCounts as $id => $count) {
            $pos = $positions[$id];
            $topCorrectedPositions[] = [
                'id' => $pos->id,
                'title' => $pos->title,
                'total' => $count,
            ];
        }

        arsort($topCorrectedSubmitters);
        $topCorrectedSubmitters = array_slice($topCorrectedSubmitters, 0, 20, true);

        if (!empty($topCorrectedSubmitters)) {
            $peopleById = DB::table('person')
                ->select('id', 'callsign')
                ->whereIntegerInRaw('id', array_keys($topCorrectedSubmitters))
                ->get()
                ->keyBy('id');
        } else {
            $peopleById = [];
        }

        $topCorrectedPeople = [];
        foreach ($topCorrectedSubmitters as $id => $total) {
            $person = $peopleById[$id];
            $topCorrectedPeople[] = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'total' => $total,
            ];
        }

        $reviewers = [];
        foreach ($people as $person) {
            $stats = $reviewerById[$person->id];
            $reviewer = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'corrections_approved' => $stats['corrections_approved'],
                'corrections_rejected' => $stats['corrections_rejected'],
                'corrections_total' => $stats['corrections_approved'] + $stats['corrections_rejected'],
                'missing_approved' => $stats['missing_approved'],
                'missing_rejected' => $stats['missing_rejected'],
                'missing_total' => $stats['missing_approved'] + $stats['missing_rejected'],
            ];
            $reviewer['grand_total'] = $reviewer['corrections_total'] + $reviewer['missing_total'];
            $reviewers[] = $reviewer;
        }

        return [
            'timesheet_total' => Timesheet::whereYear('on_duty', $year)->count(),
            'entries_deleted' => $deletedCount,
            'missing_requests' => [
                'approved' => $missingStatuses[TimesheetMissing::APPROVED]?->total ?? 0,
                'rejected' => $missingStatuses[TimesheetMissing::REJECTED]?->total ?? 0,
                'total' => DB::table('timesheet_missing')
                    ->whereYear('on_duty', $year)
                    ->whereColumn('person_id', '!=', 'reviewer_person_id')
                    ->count(),
                'top_positions' => $topMissing,
                'top_people' => $topMissingPeople,
            ],
            'correction_requests' => [
                'approved' => $approved,
                'rejected' => $rejected,
                'top_positions' => $topCorrectedPositions,
                'top_people' => $topCorrectedPeople,
            ],
            'reviewers' => $reviewers,
        ];
    }

    private static function setupReviewer(&$reviewers, $id): void
    {
        if (isset($reviewers[$id])) {
            return;
        }

        $reviewers[$id] = [
            'missing_approved' => 0,
            'missing_rejected' => 0,
            'corrections_approved' => 0,
            'corrections_rejected' => 0,
        ];
    }
}