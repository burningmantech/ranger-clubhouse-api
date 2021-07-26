<?php

namespace App\Lib\Reports;

use App\Models\Slot;

class ShiftSignupsReport
{

    /**
     * Report on all active shifts signups for a given year. Shifts are grouped together by position and
     * calculate a summary of empty shifts and total sign ups.
     *
     * @param int $year
     * @return array
     */

    public static function execute(int $year): array
    {
        $rows = Slot::whereYear('begins', $year)
            ->where('active', true)
            ->orderBy('begins')
            ->with(['position:id,title'])
            ->get();

        $rowsByPositions = $rows->groupBy('position_id');

        $positions = [];
        foreach ($rowsByPositions as $positionId => $shifts) {
            $totalMax = 0;
            $totalSignedUp = 0;
            $emptyShifts = 0;
            foreach ($shifts as $shift) {
                $totalMax += $shift->max;
                $totalSignedUp += $shift->signed_up;
                if ($shift->signed_up == 0) {
                    $emptyShifts++;
                }
            }

            $positions[] = [
                'id' => $positionId,
                'title' => $shifts[0]->position->title,
                'total_signed_up' => $totalSignedUp,
                'total_max' => $totalMax,
                'total_empty' => $emptyShifts,
                'full_percentage' => ($totalMax > 0 ? floor(($totalSignedUp * 100) / $totalMax) : 0),
                'shifts' => $shifts->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'begins' => (string)$row->begins,
                        'ends' => (string)$row->ends,
                        'description' => $row->description,
                        'signed_up' => $row->signed_up,
                        'max' => $row->max,
                        'full_percentage' => ($row->max > 0 ? floor(($row->signed_up * 100) / $row->max) : 0)
                    ];
                })
            ];
        }

        usort($positions, fn($a, $b) => strcasecmp($a['title'], $b['title']));

        return $positions;
    }
}