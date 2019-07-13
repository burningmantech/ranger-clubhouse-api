<?php

namespace App\Lib;

use App\Models\Position;
use App\Models\Slot;
use Illuminate\Support\Facades\DB;

class ShiftReporting {
     const CALLSIGNS = 1;
     const COUNT = 2;

     const PRE_EVENT= [
         [ Position::OOD, 'OOD', self::CALLSIGNS ],
         [ Position::RSC_SHIFT_LEAD_PRE_EVENT, 'RSL', self::CALLSIGNS ],
         [ Position::LEAL_PRE_EVENT, 'LEAL', self::CALLSIGNS ],
         [ Position::DIRT_PRE_EVENT,  'Dirt', self::CALLSIGNS ],
         [ Position::DIRT_PRE_EVENT, 'Dirt Count', self::COUNT ]
     ];

     const HQ = [
         [ [ Position::HQ_LEAD, Position::HQ_LEAD_PRE_EVENT ], 'Lead', self::CALLSIGNS ],
         [ Position::HQ_SHORT, 'Short', self::CALLSIGNS ],
         [ [ Position::HQ_WINDOW, Position::HQ_WINDOW_PRE_EVENT ], 'Window', self::CALLSIGNS ],
         [ Position::HQ_RUNNER, 'Runner', self::CALLSIGNS ]
     ];

     const GREEN_DOT = [
        [ Position::GREEN_DOT_LEAD, 'GDL', self::CALLSIGNS ],
        [ Position::GREEN_DOT_LEAD_INTERN, 'GDL Intern', self::CALLSIGNS ],
        [ Position::DIRT_GREEN_DOT, 'GD Dirt', self::CALLSIGNS ],
        [ Position::GREEN_DOT_MENTOR, 'GD Mentors', self::CALLSIGNS ],
        [ Position::GREEN_DOT_MENTEE, 'GD Mentees', self::CALLSIGNS ],
        [ Position::SANCTUARY, 'Sanctuary', self::CALLSIGNS ],
        [ Position::SANCTUARY_MENTEE, 'Sanctuary Mentee', self::CALLSIGNS ],
        // [ Position::SANCTUARY_HOST, 'Sanctuary Host', self::CALLSIGNS ] -- GD cadre deprecated the position for 2019.
        [ Position::GERLACH_PATROL_GREEN_DOT, 'Gerlach GD', self::CALLSIGNS ],
     ];

     const GERLACH_PATROL = [
         [ Position::GERLACH_PATROL_LEAD, 'GP Lead', self::CALLSIGNS ],
         [ Position::GERLACH_PATROL, 'GP Rangers', self::CALLSIGNS ],
         [ Position::GERLACH_PATROL_GREEN_DOT, 'GP Green Dot', self::CALLSIGNS ]
     ];

     const ECHELON = [
         [ Position::ECHELON_FIELD_LEAD, 'Echelon Lead', self::CALLSIGNS ],
         [ Position::ECHELON_FIELD, 'Echelon Field', self::CALLSIGNS ],
         [ Position::ECHELON_FIELD_LEAD_TRAINING, 'Training', self::CALLSIGNS ],
     ];

     const RSCI_MENTOR = [
        [ Position::RSCI_MENTOR, 'RSCI Mentors', self::CALLSIGNS ],
        [ Position::RSCI_MENTEE, 'RSCI Mentees', self::CALLSIGNS ]
     ];

     const INTERCEPT = [
         [ Position::INTERCEPT_DISPATCH, 'Dispatch', self::CALLSIGNS ],
         // Intercept Operator position deprecated in favor of Operator shifts matching Intercept hours
         [ [ Position::INTERCEPT_OPERATOR, Position::OPERATOR ], 'Operator', self::CALLSIGNS ],
         [ Position::INTERCEPT, 'Interceptors', self::CALLSIGNS ],
         [ Position::INTERCEPT, 'Count', self::COUNT ]
     ];

     const COMMAND = [
        [ Position::OOD, 'OOD', self::CALLSIGNS ],
        [ Position::DEPUTY_OOD, 'DOOD', self::CALLSIGNS ],
        [ Position::RSC_SHIFT_LEAD, 'RSL', self::CALLSIGNS ],
        [ Position::RSCI, 'RSCI', self::CALLSIGNS ],
        [ Position::RSCI_MENTEE, 'RSCIM', self::CALLSIGNS ],
        [ Position::RSC_WESL, 'WESL', self::CALLSIGNS ],
        [ Position::OPERATOR, 'Opr', self::CALLSIGNS ],
        [ Position::RSC_WESL, 'WESL', self::CALLSIGNS ],
        [ [ Position::TROUBLESHOOTER, Position::TROUBLESHOOTER_RIDE_ALONG ], 'TS', self::CALLSIGNS ],
        [ [ Position::LEAL, Position::LEAL_PARTNER ], 'LEAL', self::CALLSIGNS ],
        [ [ Position::GREEN_DOT_LEAD, Position::GREEN_DOT_LEAD_INTERN ], 'GDL', self::CALLSIGNS ],
        [ Position::TOW_TRUCK_DRIVER, 'Tow', self::CALLSIGNS ],
        [ Position::SANCTUARY, 'Sanc', self::CALLSIGNS ],
        //[ Position::SANCTUARY_HOST, 'SancHst', self::CALLSIGNS ],
        [ Position::GERLACH_PATROL_LEAD, 'GerPatLd', self::CALLSIGNS ],
        [ [ Position::GERLACH_PATROL, Position::GERLACH_PATROL_GREEN_DOT ], 'GerPat', self::CALLSIGNS ],
        [ [ Position::DIRT, Position::DIRT_SHINY_PENNY, Position::DIRT_POST_EVENT ], 'Dirt', self::COUNT ],
        [ Position::DIRT_GREEN_DOT, 'GD', self::COUNT ],
        [ [ Position::RNR, Position::RNR_RIDE_ALONG ], 'RNR', self::COUNT ],
        [ [ Position::BURN_PERIMETER, Position::ART_CAR_WRANGLER, Position::BURN_COMMAND_TEAM, Position::BURN_QUAD_LEAD, Position::SANDMAN], 'Burn', self::COUNT ]
    ];

     const PERIMETER = [
         [ Position::BURN_COMMAND_TEAM, 'Burn Command', self::CALLSIGNS ],
         [ Position::BURN_QUAD_LEAD, 'Quad Lead', self::CALLSIGNS ],
         [ Position::SANDMAN, 'Sandman', self::CALLSIGNS ],
         [ Position::ART_CAR_WRANGLER, 'Art Car', self::CALLSIGNS ],
         [ Position::BURN_PERIMETER, 'Perimeter', self::CALLSIGNS ],
         [ Position::BURN_PERIMETER, 'Perimeter #', self::COUNT ],
         [ [ Position::BURN_COMMAND_TEAM, Position::BURN_QUAD_LEAD, Position::SANDMAN, Position::ART_CAR_WRANGLER, Position::BURN_PERIMETER ], 'Total #', self::COUNT ],
     ];

     const MENTORS = [
         [ Position::MENTOR_LEAD, 'Lead', self::CALLSIGNS ],
         [ Position::MENTOR_SHORT, 'Short', self::CALLSIGNS ],
         [ Position::MENTOR, 'Mentor', self::CALLSIGNS ],
         [ Position::MENTOR_MITTEN, 'Mitten', self::CALLSIGNS ],
         [ Position::MENTOR_APPRENTICE, 'Apprentice', self::CALLSIGNS ],
         [ Position::MENTOR_KHAKI, 'Khaki', self::CALLSIGNS ],
         [ Position::MENTOR_RADIO_TRAINER, 'Radio', self::CALLSIGNS ],
         [ Position::ALPHA, 'Alpha', self::CALLSIGNS ],
         [ Position::QUARTERMASTER, 'QM', self::CALLSIGNS ],
     ];

    /*
     * The various type which can be reported on.
     *
     * The position is the "shift base" used to determine the date range.
     */

    const COVERAGE_TYPES = [
        'perimeter'      => [ Position::BURN_PERIMETER, self::PERIMETER ],
        'intercept'      => [ Position::INTERCEPT, self::INTERCEPT ],
        'hq'             => [ [ Position::HQ_SHORT, Position::HQ_WINDOW_PRE_EVENT ], self::HQ ],
        'gd'             => [ Position::DIRT_GREEN_DOT, self::GREEN_DOT ],
        'mentor'         => [ Position::ALPHA, self::MENTORS ],
        'rsci-mentor'    => [ Position::RSCI_MENTOR, self::RSCI_MENTOR ],
        'gerlach-patrol' => [ Position::GERLACH_PATROL, self::GERLACH_PATROL ],
        'echelon'        => [ Position::ECHELON_FIELD, self::ECHELON ],
        'pre-event'      => [ Position::DIRT_PRE_EVENT, self::PRE_EVENT ],
        'command'        => [ [ Position::DIRT, Position::DIRT_POST_EVENT ], self::COMMAND ],
    ];

    public static function retrieveShiftCoverageByYearType($year, $type)
    {
        if (!isset(self::COVERAGE_TYPES[$type])) {
            throw new \InvalidArgumentException("Unknown type $type");
        }

        list ($basePositionId, $coverage) =  self::COVERAGE_TYPES[$type];

        $shifts = self::getShiftsByPosition($year, $basePositionId);

        $periods = [];
        foreach ($shifts as $shift) {
            $positions = [];
            foreach ($coverage as $post) {
                list ($positionId, $shortTitle, $flag) = $post;

                $shifts = self::getSignUps($positionId, $shift->begins_epoch, $shift->ends_epoch, $flag);

                $positions[] = [
                    'position_id' => $positionId,
                    'type'        => ($flag == self::COUNT ? 'count' : 'people'),
                    'shifts'      => $shifts
                ];
            }

            $periods[] = [
                'begins'    => (string) $shift->begins_epoch,
                'ends'      => (string) $shift->ends_epoch,
                'date'      => $shift->begins_epoch->toDateString(),
                'positions' => $positions,
            ];
        }

        $columns = [];
        foreach ($coverage as $post) {
            $columns[] = [
                'position_id' => $post[0],
                'short_title' => $post[1]
            ];
        }

        return [
            'columns' => $columns,
            'periods' => $periods
        ];
    }

    public static function getShiftsByPosition($year, $positionId)
    {
        $sql = Slot::whereYear('begins', $year);

        if (is_array($positionId)) {
            $sql->whereIn('position_id', $positionId);
        } else {
            $sql->where('position_id', $positionId);
        }

        $slots = $sql->orderBy('begins')->get();

        foreach ($slots as $slot) {
            $slot->begins_epoch = self::adjustToHourBoundary($slot->begins);
            $slot->ends_epoch = self::adjustToHourBoundary($slot->ends);
        }

        return $slots;
    }

    /*
	 * Adjust a shift to an hour boundary
	 */

    public static function adjustToHourBoundary($epoch)
    {
        $epoch = $epoch->clone();
        if ($epoch->minute >= 30) {
            $epoch->addHour();
        }
        $epoch->minute = 0;
        $epoch->second = 0;
        return $epoch;
    }

    /*
	 * Return list of Rangers scheduled for a given position
	 * and time range.
	 */

    public static function getSignUps($positionId, $begins, $ends, $flag)
    {
        $begins = $begins->clone()->addMinutes(90);
        $ends = $ends->clone()->subMinutes(90);

        $sql = DB::table('person_slot')
                ->join('person', 'person.id', 'person_slot.person_id')
                ->join('slot', 'slot.id', 'person_slot.slot_id')
                ->where(function ($q) use ($begins, $ends) {
                    // Shift spans the entire period
                    $q->where(function($q) use ($begins, $ends) {
                        $q->where('slot.begins', '<=', $begins);
                        $q->where('slot.ends', '>=', $ends);
                    });
                    // Shift happens within the period
                    $q->orwhere(function($q) use ($begins, $ends) {
                        $q->where('slot.begins', '>=', $begins);
                        $q->where('slot.ends', '<=', $ends);
                    });
                    // Shift ends within the period
                    $q->orwhere(function($q) use ($begins, $ends) {
                        $q->where('slot.ends', '>', $begins);
                        $q->where('slot.ends', '<=', $ends);
                    });
                    // Shift begins within the period
                    $q->orwhere(function($q) use ($begins, $ends) {
                        $q->where('slot.begins', '>=', $begins);
                        $q->where('slot.begins', '<', $ends);
                    });
                });

        if (is_array($positionId)) {
            $sql->whereIn('slot.position_id', $positionId);
        } else {
            $sql->where('slot.position_id', $positionId);
        }

        if ($flag == self::COUNT) {
            return $sql->count('person.id');
        }

        $rows = $sql->select('person.id', 'callsign', 'slot.begins', 'slot.ends')
                ->orderBy('slot.begins')
                ->orderBy('person.callsign')
                ->get();

        $prevBegins = null;

        $shifts = [];
        $groups = $rows->groupBy('begins');

        foreach ($groups as $begins => $rows) {
            $people = $rows->map(function ($row) {
                return [
                    'id'       => $row->id,
                    'callsign' => $row->callsign,
                ];
            });

            $shifts[] = [
                'people'    => $people,
                'begins'    => (string) $begins,
                'ends'      => (string) $rows[0]->ends
            ];
        }

        return $shifts;
    }

    /*
     * Shift Signup report
     */

    public static function retrieveShiftSignupsForYear($year)
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

        usort($positions, function ($a,$b) {
            return strcasecmp($a['title'], $b['title']);
        });

        return $positions;
    }
}
