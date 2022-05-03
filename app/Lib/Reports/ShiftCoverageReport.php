<?php


namespace App\Lib\Reports;


use App\Models\Position;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShiftCoverageReport
{
    const CALLSIGNS = 1;
    const COUNT = 2;

    const PRE_EVENT = [
        [Position::OOD, 'OOD', self::CALLSIGNS],
        [Position::RSC_SHIFT_LEAD_PRE_EVENT, 'RSL', self::CALLSIGNS],
        [Position::LEAL_PRE_EVENT, 'LEAL', self::CALLSIGNS],
        [Position::DIRT_PRE_EVENT, 'Dirt', self::CALLSIGNS],
        [Position::DIRT_PRE_EVENT, 'Dirt Count', self::COUNT]
    ];

    const HQ = [
        [[Position::HQ_LEAD, Position::HQ_LEAD_PRE_EVENT], 'Lead', self::CALLSIGNS],
        [Position::HQ_SHORT, 'Short', self::CALLSIGNS],
        [[Position::HQ_WINDOW, Position::HQ_WINDOW_PRE_EVENT], 'Window', self::CALLSIGNS],
        [Position::HQ_RUNNER, 'Runner', self::CALLSIGNS]
    ];

    const GREEN_DOT = [
        [Position::GREEN_DOT_LEAD, 'GDL', self::CALLSIGNS],
        [Position::GREEN_DOT_LEAD_INTERN, 'GDL Intern', self::CALLSIGNS],
        [Position::DIRT_GREEN_DOT, 'GD Dirt', self::CALLSIGNS],
        [Position::GREEN_DOT_MENTOR, 'GD Mentors', self::CALLSIGNS],
        [Position::GREEN_DOT_MENTEE, 'GD Mentees', self::CALLSIGNS],
        [Position::SANCTUARY, 'Sanctuary', self::CALLSIGNS],
        [Position::SANCTUARY_MENTEE, 'Sanc Mentee', self::CALLSIGNS],
        // [ Position::SANCTUARY_HOST, 'Sanctuary Host', self::CALLSIGNS ] -- GD cadre deprecated the position for 2019.
        [Position::GERLACH_PATROL_GREEN_DOT, 'Gerlach GD', self::CALLSIGNS],
    ];

    const GERLACH_PATROL = [
        [Position::GERLACH_PATROL_LEAD, 'GP Lead', self::CALLSIGNS],
        [Position::GERLACH_PATROL, 'GP Rangers', self::CALLSIGNS],
        [Position::GERLACH_PATROL_GREEN_DOT, 'GP Green Dot', self::CALLSIGNS]
    ];

    const ECHELON = [
        [Position::ECHELON_FIELD_LEAD, 'Echelon Lead', self::CALLSIGNS],
        [Position::ECHELON_FIELD, 'Echelon Field', self::CALLSIGNS],
        [Position::ECHELON_FIELD_LEAD_TRAINING, 'Lead Training', self::CALLSIGNS],
    ];

    const RSCI_MENTOR = [
        [Position::RSCI_MENTOR, 'RSCI Mentors', self::CALLSIGNS],
        [Position::RSCI_MENTEE, 'RSCI Mentees', self::CALLSIGNS]
    ];

    const INTERCEPT = [
        [Position::INTERCEPT_DISPATCH, 'Dispatch', self::CALLSIGNS],
        // Intercept Operator position deprecated in favor of Operator shifts matching Intercept hours
        [[Position::INTERCEPT_OPERATOR, Position::OPERATOR], 'Operator', self::CALLSIGNS],
        [Position::INTERCEPT, 'Interceptors', self::CALLSIGNS],
        [Position::INTERCEPT, 'Count', self::COUNT]
    ];

    const COMMAND = [
        [Position::OOD, 'OOD', self::CALLSIGNS],
        [Position::DEPUTY_OOD, 'DOOD', self::CALLSIGNS],
        [Position::RSC_SHIFT_LEAD, 'RSL', self::CALLSIGNS],
        [Position::RSCI, 'RSCI', self::CALLSIGNS],
        [Position::RSCI_MENTEE, 'RSCIM', self::CALLSIGNS],
        [Position::RSC_WESL, 'WESL', self::CALLSIGNS],
        [[ Position::OPERATOR, Position::OPERATOR_SMOOTH], 'Opr', self::CALLSIGNS, [ Position::OPERATOR_SMOOTH => 'Smooth']],
        [[Position::TROUBLESHOOTER, Position::TROUBLESHOOTER_MENTEE], 'TS', self::CALLSIGNS, [Position::TROUBLESHOOTER_MENTEE => 'Mentee']],
        [[Position::LEAL, Position::LEAL_PARTNER], 'LEAL', self::CALLSIGNS, [Position::LEAL_PARTNER => 'Partner']],
        [[Position::GREEN_DOT_LEAD, Position::GREEN_DOT_LEAD_INTERN], 'GDL', self::CALLSIGNS, [Position::GREEN_DOT_LEAD_INTERN => 'Intern']],
        [[Position::TOW_TRUCK_DRIVER, Position::TOW_TRUCK_MENTEE], 'Tow', self::CALLSIGNS, [Position::TOW_TRUCK_MENTEE => 'Mentee']],
        [[Position::SANCTUARY, Position::SANCTUARY_MENTEE], 'Sanc', self::CALLSIGNS, [Position::SANCTUARY_MENTEE => 'Mentee']],
        //[ Position::SANCTUARY_HOST, 'SancHst', self::CALLSIGNS ],
        [Position::GERLACH_PATROL_LEAD, 'GerPatLd', self::CALLSIGNS],
        [[Position::GERLACH_PATROL, Position::GERLACH_PATROL_GREEN_DOT], 'GerPat', self::CALLSIGNS],
        [[Position::DIRT, Position::DIRT_SHINY_PENNY, Position::DIRT_POST_EVENT], 'Dirt', self::COUNT],
        [Position::DIRT_GREEN_DOT, 'GD', self::COUNT],
        [[Position::RNR, Position::RNR_RIDE_ALONG], 'RNR', self::COUNT],
        [[Position::BURN_PERIMETER, Position::ART_CAR_WRANGLER, Position::BURN_COMMAND_TEAM, Position::BURN_QUAD_LEAD, Position::SANDMAN], 'Burn', self::COUNT]
    ];

    const PERIMETER = [
        [Position::BURN_COMMAND_TEAM, 'Burn Command', self::CALLSIGNS],
        // As of 2019 the Burn Quad Lead position has not been used
        // [ Position::BURN_QUAD_LEAD, 'Quad Lead', self::CALLSIGNS ],
        [Position::SANDMAN, 'Sandman', self::CALLSIGNS],
        [Position::ART_CAR_WRANGLER, 'Art Car', self::CALLSIGNS],
        [Position::BURN_PERIMETER, 'Perimeter', self::CALLSIGNS],
        [Position::BURN_PERIMETER, 'Perimeter #', self::COUNT],
        [[Position::BURN_COMMAND_TEAM, Position::BURN_QUAD_LEAD, Position::SANDMAN, Position::ART_CAR_WRANGLER, Position::BURN_PERIMETER], 'Total #', self::COUNT],
        // Troubleshooter not included in count because some are in the city
        [Position::TROUBLESHOOTER, 'Troubleshooter', self::CALLSIGNS],
    ];

    const MENTORS = [
        [Position::MENTOR_LEAD, 'Lead', self::CALLSIGNS],
        [Position::MENTOR_SHORT, 'Short', self::CALLSIGNS],
        [Position::MENTOR, 'Mentor', self::CALLSIGNS],
        [Position::MENTOR_MITTEN, 'Mitten', self::CALLSIGNS],
        [Position::MENTOR_APPRENTICE, 'Apprentice', self::CALLSIGNS],
        [Position::MENTOR_KHAKI, 'Khaki', self::CALLSIGNS],
        [Position::MENTOR_RADIO_TRAINER, 'Radio', self::CALLSIGNS],
        [Position::ALPHA, 'Alpha', self::CALLSIGNS],
        [Position::QUARTERMASTER, 'QM', self::CALLSIGNS],
    ];

    /*
      * The various type which can be reported on.
      *
      * The position is the "shift base" used to determine the date range.
      */

    const COVERAGE_TYPES = [
        'perimeter' => [Position::BURN_PERIMETER, self::PERIMETER],
        'intercept' => [Position::INTERCEPT, self::INTERCEPT],
        'hq' => [[Position::HQ_SHORT, Position::HQ_WINDOW_PRE_EVENT], self::HQ],
        'gd' => [Position::DIRT_GREEN_DOT, self::GREEN_DOT],
        'mentor' => [Position::ALPHA, self::MENTORS],
        'rsci-mentor' => [Position::RSCI_MENTOR, self::RSCI_MENTOR],
        'gerlach-patrol' => [Position::GERLACH_PATROL, self::GERLACH_PATROL],
        'echelon' => [Position::ECHELON_FIELD, self::ECHELON],
        'pre-event' => [Position::DIRT_PRE_EVENT, self::PRE_EVENT],
        'command' => [[Position::DIRT, Position::DIRT_POST_EVENT,Position::OPERATOR_SMOOTH], self::COMMAND],
    ];

    public static function execute(int $year, string $type): array
    {
        if (!isset(self::COVERAGE_TYPES[$type])) {
            throw new InvalidArgumentException("Unknown type $type");
        }

        list($basePositionId, $coverage) = self::COVERAGE_TYPES[$type];

        $shifts = self::getShiftsByPosition($year, $basePositionId);

        $periods = [];
        foreach ($shifts as $shift) {
            $positions = [];
            foreach ($coverage as $post) {
                list($positionId, $shortTitle, $flag) = $post;
                $parenthetical = empty($post[3]) ? array() : $post[3];

                $shifts = self::getSignUps($positionId, $shift->begins_epoch, $shift->ends_epoch, $flag, $parenthetical);

                $positions[] = [
                    'position_id' => $positionId,
                    'type' => ($flag == self::COUNT ? 'count' : 'people'),
                    'shifts' => $shifts
                ];
            }

            $periods[] = [
                'begins' => (string)$shift->begins_epoch,
                'ends' => (string)$shift->ends_epoch,
                'date' => $shift->begins_epoch,
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
        $sql = DB::table('slot')
            ->select('slot.*',
                DB::raw("DATE_FORMAT(DATE_ADD(begins, INTERVAL 30 MINUTE),'%Y-%m-%d %H:00:00') as begins_epoch"),
                DB::raw("DATE_FORMAT(DATE_ADD(ends, INTERVAL 30 MINUTE),'%Y-%m-%d %H:00:00') as ends_epoch")
            )
            ->whereYear('begins', $year);

        if (is_array($positionId)) {
            $sql->whereIn('position_id', $positionId);
        } else {
            $sql->where('position_id', $positionId);
        }

        return $sql->orderBy('begins')->get();
    }

    /*
      * Return list of Rangers scheduled for a given position
      * and time range.
      */

    public static function getSignUps($positionId, $begins, $ends, $flag, $parenthetical)
    {
        $begins = Carbon::parse($begins)->addMinutes(90);
        $ends = Carbon::parse($ends)->subMinutes(90);

        $sql = DB::table('slot')
            ->join('person_slot', 'slot.id', 'person_slot.slot_id')
            ->join('person', 'person.id', 'person_slot.person_id')
            ->where(function ($q) use ($begins, $ends) {
                // Shift spans the entire period
                $q->where(function ($q) use ($begins, $ends) {
                    $q->where('slot.begins', '<=', $begins);
                    $q->where('slot.ends', '>=', $ends);
                });
                // Shift happens within the period
                $q->orwhere(function ($q) use ($begins, $ends) {
                    $q->where('slot.begins', '>=', $begins);
                    $q->where('slot.ends', '<=', $ends);
                });
                // Shift ends within the period
                $q->orwhere(function ($q) use ($begins, $ends) {
                    $q->where('slot.ends', '>', $begins);
                    $q->where('slot.ends', '<=', $ends);
                });
                // Shift begins within the period
                $q->orwhere(function ($q) use ($begins, $ends) {
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

        $rows = $sql->select('person.id', 'callsign', 'callsign_pronounce', 'slot.begins', 'slot.ends', 'slot.position_id')
            ->orderBy('slot.begins')
            ->orderBy('slot.ends', 'desc')
            ->orderBy('person.callsign')
            ->get();

        $shifts = [];
        $groups = $rows->groupBy('begins');

        foreach ($groups as $begins => $rows) {
            $people = $rows->map(function ($row) use ($parenthetical) {
                $i = [
                    'id' => $row->id,
                    'callsign' => $row->callsign,
                    'parenthetical' => $parenthetical[$row->position_id] ?? '',
                ];

                if (!empty($row->callsign_pronounce)) {
                    $i['callsign_pronounce'] = $row->callsign_pronounce;
                }

                return $i;
            });

            $shifts[] = [
                'people' => $people,
                'begins' => (string)$begins,
                'ends' => (string)$rows[0]->ends
            ];
        }

        return $shifts;
    }
}