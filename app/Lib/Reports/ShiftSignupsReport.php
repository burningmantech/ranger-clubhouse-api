<?php

namespace App\Lib\Reports;

use App\Models\Slot;

class ShiftSignupsReport
{
    /*
     * Shift Signup report
     */

    public static function execute(int $year)
    {
        $rows = Slot::select(
            'slot.id',
            'begins',
            'ends',
            'description',
            'signed_up',
            'max',
            'position_id'
        )->whereYear('begins', $year)
            ->with([ 'position:id,title'])
            ->orderBy('begins')
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
                'id'              => $positionId,
                'title'           => $shifts[0]->position->title,
                'total_signed_up' => $totalSignedUp,
                'total_max'       => $totalMax,
                'total_empty'     => $emptyShifts,
                'full_percentage' => ($totalMax > 0 ? floor(($totalSignedUp * 100) / $totalMax) : 0),
                'shifts'          => $shifts->map(function ($row) {
                    return [
                        'id'          => $row->id,
                        'begins'      => (string) $row->begins,
                        'ends'        => (string) $row->ends,
                        'description' => $row->description,
                        'signed_up'   => $row->signed_up,
                        'max'         => $row->max,
                        'full_percentage' => ($row->max > 0 ? floor(($row->signed_up * 100)/ $row->max) : 0)
                    ];
                })
            ];
        }

        usort($positions, function ($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });

        return $positions;
    }

}