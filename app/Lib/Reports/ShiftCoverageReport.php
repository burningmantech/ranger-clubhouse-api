<?php

namespace App\Lib\Reports;

use App\Models\Position;
use App\Models\Slot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShiftCoverageReport
{
    const COUNT_ONLY = true;

    const PRE_EVENT = [
        [Position::OOD, 'OOD'],
        [Position::RSC_SHIFT_LEAD_PRE_EVENT, 'RSL'],
        [Position::TROUBLESHOOTER_PRE_EVENT, 'TS'],
        [Position::DIRT_PRE_EVENT, 'Dirt'],
        [Position::DIRT_PRE_EVENT, 'Dirt Count', self::COUNT_ONLY],
    ];

    const POST_EVENT = [
        [Position::OOD, 'OOD'],
        [Position::RSC_SHIFT_LEAD, 'RSL'],
        [Position::TROUBLESHOOTER, 'TS'],
        [Position::DIRT_GREEN_DOT, 'GD'],
        [Position::DPW_RANGER, 'DPW'],
        [Position::DIRT_POST_EVENT, 'Dirt'],
        [Position::DIRT_POST_EVENT, 'Dirt Count', self::COUNT_ONLY],
    ];

    const HQ = [
        [[Position::HQ_LEAD, Position::HQ_LEAD_PRE_EVENT], 'Lead'],
        [Position::HQ_SHORT, 'Short'],
        [[Position::HQ_WINDOW, Position::HQ_WINDOW_PRE_EVENT], 'Window'],
        [Position::HQ_RUNNER, 'Runner']
    ];

    const GREEN_DOT = [
        [Position::GREEN_DOT_LEAD, 'GDL'],
        [Position::GREEN_DOT_LEAD_INTERN, 'GDL Intern'],
        [Position::DIRT_GREEN_DOT, 'GD Dirt'],
        [Position::GREEN_DOT_MENTOR, 'GD Mentors'],
        [Position::GREEN_DOT_MENTEE, 'GD Mentees'],
        [Position::SANCTUARY, 'Sanctuary'],
        [Position::SANCTUARY_MENTEE, 'Sanc Mentee'],
        // [ Position::SANCTUARY_HOST, 'Sanctuary Host' ] -- GD cadre deprecated the position for 2019.
        [Position::GERLACH_PATROL_GREEN_DOT, 'Gerlach GD'],
    ];

    const GERLACH_PATROL = [
        [Position::GERLACH_PATROL_LEAD, 'GP Lead'],
        [Position::GERLACH_PATROL, 'GP Rangers'],
        [Position::GERLACH_PATROL_GREEN_DOT, 'GP Green Dot']
    ];

    const ECHELON = [
        [Position::ECHELON_FIELD_LEAD, 'Echelon Lead'],
        [Position::ECHELON_FIELD, 'Echelon Field'],
        [Position::ECHELON_FIELD_LEAD_TRAINING, 'Lead Training'],
    ];

    const RSCI_MENTOR = [
        [Position::RSCI_MENTOR, 'RSCI Mentors'],
        [Position::RSCI_MENTEE, 'RSCI Mentees']
    ];

    const INTERCEPT = [
        [Position::INTERCEPT_DISPATCH, 'Dispatch'],
        // Intercept Operator position deprecated in favor of Operator shifts matching Intercept hours
        [[Position::INTERCEPT_OPERATOR, Position::OPERATOR], 'Operator'],
        [Position::INTERCEPT, 'Interceptors', self::COUNT_ONLY],
        [Position::INTERCEPT, 'Count', self::COUNT_ONLY],
    ];

    const COMMAND = [
        [Position::OOD, 'OOD'],
        [Position::DEPUTY_OOD, 'DOOD'],
        [Position::RSC_SHIFT_LEAD, 'RSL'],
        [Position::RSCI, 'RSCI'],
        [Position::RSCI_MENTEE, 'RSCIM'],
        [Position::RSC_WESL, 'WESL'],
        [[Position::OPERATOR, Position::OPERATOR_SMOOTH], 'Opr', false, [Position::OPERATOR_SMOOTH => 'Smooth']],
        [[Position::TROUBLESHOOTER, Position::TROUBLESHOOTER_MENTEE, Position::TROUBLESHOOTER_LEAL],
            'TS',
            false,
            [
                Position::TROUBLESHOOTER_MENTEE => 'Mentee',
                Position::TROUBLESHOOTER_LEAL => 'TSLEAL'
            ]
        ],
        [[Position::LEAL, Position::LEAL_PARTNER], 'LEAL', false, [Position::LEAL_PARTNER => 'Partner']],
        [[Position::GREEN_DOT_LEAD, Position::GREEN_DOT_LEAD_INTERN], 'GDL', false, [Position::GREEN_DOT_LEAD_INTERN => 'Intern']],
        [[Position::TOW_TRUCK_DRIVER, Position::TOW_TRUCK_MENTEE], 'Tow', false, [Position::TOW_TRUCK_MENTEE => 'Mentee']],
        [[Position::SANCTUARY, Position::SANCTUARY_MENTEE], 'Sanc', false, [Position::SANCTUARY_MENTEE => 'Mentee']],
        //[ Position::SANCTUARY_HOST, 'SancHst' ],
        [Position::GERLACH_PATROL_LEAD, 'GerPatLd'],
        [[Position::GERLACH_PATROL, Position::GERLACH_PATROL_GREEN_DOT], 'GerPat'],
        [[Position::DIRT, Position::DIRT_SHINY_PENNY], 'Dirt', self::COUNT_ONLY],
        [Position::DIRT_GREEN_DOT, 'GD', self::COUNT_ONLY],
        [[Position::RNR, Position::RNR_RIDE_ALONG], 'RNR', self::COUNT_ONLY],
        [[Position::BURN_PERIMETER, Position::ART_CAR_WRANGLER, Position::BURN_COMMAND_TEAM, Position::BURN_QUAD_LEAD, Position::SANDMAN], 'Burn', self::COUNT_ONLY]
    ];

    const PERIMETER = [
        [Position::BURN_COMMAND_TEAM, 'Burn Command'],
        // As of 2019 the Burn Quad Lead position has not been used
        // [ Position::BURN_QUAD_LEAD, 'Quad Lead' ],
        [Position::SANDMAN, 'Sandman'],
        [Position::ART_CAR_WRANGLER, 'Art Car'],
        [Position::BURN_PERIMETER, 'Perimeter'],
        [Position::BURN_PERIMETER, 'Perimeter #', self::COUNT_ONLY],
        [
            [Position::BURN_COMMAND_TEAM, Position::BURN_QUAD_LEAD, Position::SANDMAN, Position::ART_CAR_WRANGLER, Position::BURN_PERIMETER],
            'Total #',
            self::COUNT_ONLY
        ],
        // Troubleshooter not included in count because some are in the city
        [Position::TROUBLESHOOTER, 'Troubleshooter'],
    ];

    const MENTORS = [
        [Position::MENTOR_LEAD, 'Lead'],
        [Position::MENTOR_SHORT, 'Short'],
        [Position::MENTOR, 'Mentor'],
        [Position::MENTOR_MITTEN, 'Mitten'],
        [Position::MENTOR_APPRENTICE, 'Apprentice'],
        [Position::MENTOR_KHAKI, 'Khaki'],
        [Position::MENTOR_RADIO_TRAINER, 'Radio'],
        [Position::ALPHA, 'Alpha'],
        [Position::QUARTERMASTER, 'QM'],
    ];

    /*
      * The various type which can be reported on.
      *
      * The position is the "shift base" used to determine the date range.
      */

    const COVERAGE_TYPES = [
        'command' => [[Position::DIRT, Position::DIRT_POST_EVENT, Position::OPERATOR_SMOOTH], self::COMMAND],
        'echelon' => [Position::ECHELON_FIELD, self::ECHELON],
        'gd' => [Position::DIRT_GREEN_DOT, self::GREEN_DOT],
        'gerlach-patrol' => [Position::GERLACH_PATROL, self::GERLACH_PATROL],
        'hq' => [[Position::HQ_SHORT, Position::HQ_WINDOW_PRE_EVENT], self::HQ],
        'intercept' => [Position::INTERCEPT, self::INTERCEPT],
        'mentor' => [[Position::MENTOR, Position::ALPHA], self::MENTORS],
        'perimeter' => [Position::BURN_PERIMETER, self::PERIMETER],
        'post-event' => [Position::DIRT_POST_EVENT, self::POST_EVENT],
        'pre-event' => [Position::DIRT_PRE_EVENT, self::PRE_EVENT],
        'rsci-mentor' => [Position::RSCI_MENTOR, self::RSCI_MENTOR],
    ];

    public static function execute(int $year, string $type): array
    {
        if (!isset(self::COVERAGE_TYPES[$type])) {
            throw new InvalidArgumentException("Unknown type $type");
        }

        list($basePositionId, $coverage) = self::COVERAGE_TYPES[$type];

        $shifts = self::getShiftsByPosition($year, $basePositionId);

        $periods = [];
        if ($shifts->isNotEmpty()) {
            $startTime = $shifts[0]->begins_epoch;
            $endTime = $shifts[count($shifts) - 1]->ends_epoch;

            $signups = [];
            foreach ($coverage as $post) {
                $signups[] = self::getSignUps($post[0], $startTime, $endTime, $post[2] ?? false);
            }

            foreach ($shifts as $shift) {
                $positions = [];
                $idx = 0;
                foreach ($coverage as $post) {
                    $isCount = $post[2] ?? false;

                    $positions[] = [
                        'position_id' => $post[0],
                        'type' => ($isCount ? 'count' : 'people'),
                        'shifts' => self::retrievePeriodSignups($shift, $signups[$idx], $post, $isCount)
                    ];
                    $idx++;
                }

                $periods[] = [
                    'begins' => (string)$shift->begins_epoch,
                    'ends' => (string)$shift->ends_epoch,
                    'date' => (string)$shift->begins_epoch,
                    'positions' => $positions,
                ];
            }
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

    /**
     * Get shifts by position for a given year.
     *
     * @param $year
     * @param $positionId
     * @return Collection
     */

    public static function getShiftsByPosition($year, $positionId): Collection
    {
        $sql = Slot::select('slot.*',
            DB::raw("DATE_FORMAT(DATE_ADD(begins, INTERVAL 30 MINUTE),'%Y-%m-%d %H:00:00') as begins_epoch"),
            DB::raw("DATE_FORMAT(DATE_ADD(ends, INTERVAL 30 MINUTE),'%Y-%m-%d %H:00:00') as ends_epoch")
        )->withCasts([
            'begins_epoch' => 'datetime',
            'ends_epoch' => 'datetime'
        ])->where('begins_year', $year);

        if (is_array($positionId)) {
            $sql->whereIn('position_id', $positionId);
        } else {
            $sql->where('position_id', $positionId);
        }

        return $sql->orderBy('begins')->get();
    }

    /**
     * Return list of Rangers scheduled for a given position and time range.
     *
     * @param $positionId
     * @param $begins
     * @param $ends
     * @param $isCount
     * @return array
     */

    public static function getSignUps($positionId, $begins, $ends, $isCount): array
    {
        $begins = Carbon::parse($begins)->addMinutes(90);
        $ends = Carbon::parse($ends)->subMinutes(90);

        $sql = DB::table('slot')
            ->select(
                'slot.id', 'position_id', 'begins', 'ends',
                DB::raw("DATE_FORMAT(DATE_ADD(begins, INTERVAL 30 MINUTE),'%Y-%m-%d %H:00:00') as begins_epoch"),
                DB::raw("DATE_FORMAT(DATE_ADD(ends, INTERVAL 30 MINUTE),'%Y-%m-%d %H:00:00') as ends_epoch")
            )->where('slot.begins_year', $begins->year)
            // Shift spans the entire period
            ->where(function ($w) use ($begins, $ends) {
                $w->where(function ($q) use ($begins, $ends) {
                    $q->where('slot.begins', '<=', $begins);
                    $q->where('slot.ends', '>=', $ends);
                })
                    // Shift happens within the period
                    ->orWhere(function ($q) use ($begins, $ends) {
                        $q->where('slot.begins', '>=', $begins);
                        $q->where('slot.ends', '<=', $ends);
                    })
                    // Shift ends within the period
                    ->orWhere(function ($q) use ($begins, $ends) {
                        $q->where('slot.ends', '>', $begins);
                        $q->where('slot.ends', '<=', $ends);
                    })
                    // Shift begins within the period
                    ->orWhere(function ($q) use ($begins, $ends) {
                        $q->where('slot.begins', '>=', $begins);
                        $q->where('slot.begins', '<', $ends);
                    });
            });


        if (is_array($positionId)) {
            $sql->whereIn('slot.position_id', $positionId);
        } else {
            $sql->where('slot.position_id', $positionId);
        }

        if ($isCount) {
            $sql->addSelect('signed_up');
        } else {
            $sql->leftJoin('person_slot', 'person_slot.slot_id', 'slot.id')
                ->leftJoin('person', 'person.id', 'person_slot.person_id')
                ->addSelect('person.id as person_id', 'person.callsign', 'person.callsign_pronounce')
                ->orderBy('slot.begins')
                ->orderBy('person.callsign');
        }

        $shifts = $sql->get();

        foreach ($shifts as $shift) {
            $shift->begins_time = Carbon::parse($shift->begins)->timestamp;
            $shift->ends_time = Carbon::parse($shift->ends)->timestamp;
            $shift->begins_epoch_time = Carbon::parse($shift->begins_epoch)->timestamp;
            $shift->ends_epoch_time = Carbon::parse($shift->ends_epoch)->timestamp;
        }

        return $shifts->toArray();
    }

    /**
     * Retrieve all the signs up (either callsign list or count) for a given period.
     *
     * @param $shift
     * @param $signups
     * @param $post
     * @param $isCount
     * @return array|int
     */

    public static function retrievePeriodSignups($shift, $signups, $post, $isCount): array|int
    {
        $parenthetical = $post[3] ?? [];

        $begins = $shift->begins_epoch->timestamp;
        $ends = $shift->ends_epoch->timestamp;

        $periodSignsUp = array_filter($signups, function ($row) use ($begins, $ends) {
            if ($row->begins_epoch_time <= $begins && $row->ends_epoch_time >= $ends) {
                return true;
            }

            if ($row->begins_epoch_time >= $begins && $row->ends_epoch_time <= $ends) {
                return true;
            }

            if ($row->ends_epoch_time > $begins && $row->ends_epoch_time <= $ends) {
                return true;
            }

            if ($row->begins_epoch_time >= $begins && $row->begins_epoch_time < $ends) {
                return true;
            }
            return false;
        });

        if ($isCount) {
            $people = 0;
            foreach ($periodSignsUp as $signup) {
                $people += $signup->signed_up;
            }

            return $people;
        }

        $shifts = [];
        foreach ($periodSignsUp as $signup) {
            if (!$signup->person_id) {
                // No signups in the slot, skip it.
                continue;
            }
            $begins = $signup->begins;
            if (!isset($shifts[$begins])) {
                $shifts[$begins] = [
                    'people' => [],
                    'begins' => $begins,
                    'ends' => $signup->ends,
                ];
            }

            $p = [
                'id' => $signup->person_id,
                'callsign' => $signup->callsign,
            ];

            $parens = $parenthetical[$signup->position_id] ?? null;
            if ($parens) {
                $p['parenthetical'] = $parens;
            }

            if (!empty($signup->callsign_pronounce)) {
                $p['callsign_pronounce'] = $signup->callsign_pronounce;
            }

            $shifts[$begins]['people'][] = $p;
        }

        ksort($shifts);

        $shifts = array_values($shifts);

        foreach ($shifts as &$s) {
            usort($s['people'], fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
        }

        return $shifts;
    }
}