<?php

namespace App\Lib\Reports;

use App\Exceptions\UnacceptableConditionException;
use App\Models\Position;
use App\Models\Slot;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShiftCoverageReport
{
    const COUNT_ONLY = true;

    /**
     * Number of minutes the global report window is trimmed at each end before the
     * coarse SQL pre-fetch in {@see self::getSignUps()}.
     *
     * NOTE: This trim is intentionally larger than EPOCH_ROUND_MINUTES so it absorbs
     * the per-period rounding skew in the interior of the report. It does mean the SQL
     * pre-fetch is NOT a strict superset of what the per-period PHP filter can match at
     * the extreme first/last edges of the whole report window; that long-standing
     * behavior is preserved deliberately (see class history).
     */
    const PERIOD_TRIM_MINUTES = 90;

    /**
     * Minutes added before truncating to the hour, used to bucket ragged shift start/end
     * times into hourly coverage periods (i.e. round to the nearest hour: +30 then floor).
     */
    const EPOCH_ROUND_MINUTES = 30;

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

    const TROUBLESHOOTERS = [
        [Position::TROUBLESHOOTER, 'Troubleshooter'],
        [Position::TROUBLESHOOTER_MENTOR, 'TS Mentor'],
        [Position::TROUBLESHOOTER_MENTEE, 'TS Mentee'],
        [Position::TROUBLESHOOTER_LEAL, 'TS LEAL'],
        [Position::TROUBLESHOOTER_PRE_EVENT, 'TS Pre-Event'],
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
        'troubleshooter' => [[Position::TROUBLESHOOTER, Position::TROUBLESHOOTER_PRE_EVENT], self::TROUBLESHOOTERS]
    ];

    /**
     * Build the coverage report payload for a coverage type and year.
     *
     * @param int $year the schedule year to report on
     * @param string $type one of the keys in {@see self::COVERAGE_TYPES}
     * @return array{columns: array<int, array{position_id: int|array, short_title: string}>, periods: array<int, array<string, mixed>>}
     * @throws UnacceptableConditionException when $type is not a known coverage type
     */
    public static function execute(int $year, string $type): array
    {
        if (!isset(self::COVERAGE_TYPES[$type])) {
            throw new UnacceptableConditionException("Unknown type $type");
        }

        [$basePositionId, $coverageDefinition] = self::COVERAGE_TYPES[$type];

        /** @var CoveragePost[] $posts */
        $posts = array_map(
            fn (array $definition): CoveragePost => CoveragePost::fromTuple($definition),
            $coverageDefinition
        );

        $shifts = self::getShiftsByPosition($year, $basePositionId);

        $periods = [];
        if ($shifts->isNotEmpty()) {
            $startTime = $shifts->first()->begins_epoch;
            $endTime = $shifts->last()->ends_epoch;

            $signupsByPost = [];
            foreach ($posts as $post) {
                $signupsByPost[] = self::getSignUps($post->positionId, $startTime, $endTime, $post->countOnly);
            }

            foreach ($shifts as $shift) {
                $positions = [];
                foreach ($posts as $idx => $post) {
                    $positions[] = [
                        'position_id' => $post->positionId,
                        'type' => $post->countOnly ? 'count' : 'people',
                        'shifts' => self::retrievePeriodSignups($shift, $signupsByPost[$idx], $post, $post->countOnly),
                    ];
                }

                $periods[] = [
                    'begins' => (string) $shift->begins_epoch,
                    'ends' => (string) $shift->ends_epoch,
                    'date' => (string) $shift->begins_epoch,
                    'positions' => $positions,
                ];
            }
        }

        $columns = [];
        foreach ($posts as $post) {
            $columns[] = [
                'position_id' => $post->positionId,
                'short_title' => $post->shortTitle,
            ];
        }

        return [
            'columns' => $columns,
            'periods' => $periods,
        ];
    }

    /**
     * Get shifts (slots) for the given position(s) within a schedule year, ordered by start time.
     *
     * Each row is decorated with begins_epoch / ends_epoch: the start/end rounded to the nearest
     * hour to bucket ragged shift times into hourly coverage periods.
     *
     * @param int $year schedule year to filter on (slot.begins_year)
     * @param int|array<int, int> $positionId a single position id or a list of position ids
     * @return Collection<int, Slot>
     */
    public static function getShiftsByPosition(int $year, int|array $positionId): Collection
    {
        $sql = Slot::select('slot.*', ...self::epochSelects())
            ->withCasts([
                'begins_epoch' => 'datetime',
                'ends_epoch' => 'datetime',
            ])
            ->where('begins_year', $year);

        if (is_array($positionId)) {
            $sql->whereIn('position_id', $positionId);
        } else {
            $sql->where('position_id', $positionId);
        }

        return $sql->orderBy('begins')->get();
    }

    /**
     * Return the list of Rangers (or signed-up counts) scheduled for the given position(s)
     * over a time range.
     *
     * This is a coarse pre-fetch across the whole report window; {@see self::retrievePeriodSignups()}
     * buckets the result into individual periods.
     *
     * NOTE: The window is trimmed by PERIOD_TRIM_MINUTES at each end. This long-standing behavior
     * is preserved; it is not a strict superset of the per-period filter at the extreme report edges.
     * NOTE: begins_year is derived from the trimmed start; preserved as-is. A window crossing a
     * calendar-year boundary would only query one year.
     *
     * @param int|array<int, int> $positionId a single position id or a list of position ids
     * @param string|Carbon $begins inclusive window start
     * @param string|Carbon $ends inclusive window end
     * @param bool $isCount when true, return signed-up counts instead of joined person rows
     * @return array<int, \stdClass> signup rows decorated with *_time / *_epoch_time unix timestamps
     */
    public static function getSignUps(int|array $positionId, string|Carbon $begins, string|Carbon $ends, bool $isCount): array
    {
        $begins = Carbon::parse($begins)->addMinutes(self::PERIOD_TRIM_MINUTES);
        $ends = Carbon::parse($ends)->subMinutes(self::PERIOD_TRIM_MINUTES);

        $sql = DB::table('slot')
            ->select(
                'slot.id', 'position_id', 'begins', 'ends',
                ...self::epochSelects()
            )
            ->where('slot.begins_year', $begins->year)
            ->where(fn (QueryBuilder $q) => self::applyOverlapPredicate($q, $begins, $ends));

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

        $rows = $sql->get();

        foreach ($rows as $row) {
            $row->begins_time = Carbon::parse($row->begins)->timestamp;
            $row->ends_time = Carbon::parse($row->ends)->timestamp;
            $row->begins_epoch_time = Carbon::parse($row->begins_epoch)->timestamp;
            $row->ends_epoch_time = Carbon::parse($row->ends_epoch)->timestamp;
        }

        return $rows->toArray();
    }

    /**
     * Retrieve all the sign-ups (either a count or a grouped callsign list) overlapping a single period.
     *
     * @param Slot|object $shift the period (carries begins_epoch / ends_epoch Carbon instances)
     * @param array<int, \stdClass> $signups pre-fetched candidate rows from {@see self::getSignUps()}
     * @param CoveragePost $post the coverage post definition
     * @param bool $isCount when true return an int count, otherwise a grouped people array
     * @return array<int, array<string, mixed>>|int
     */
    public static function retrievePeriodSignups(object $shift, array $signups, CoveragePost $post, bool $isCount): array|int
    {
        $parenthetical = $post->parenthetical;

        $periodBegins = $shift->begins_epoch->timestamp;
        $periodEnds = $shift->ends_epoch->timestamp;

        $periodSignsUp = array_filter(
            $signups,
            fn ($row): bool => self::epochOverlaps(
                $row->begins_epoch_time,
                $row->ends_epoch_time,
                $periodBegins,
                $periodEnds
            )
        );

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

            $signupBegins = $signup->begins;
            if (!isset($shifts[$signupBegins])) {
                $shifts[$signupBegins] = [
                    'people' => [],
                    'begins' => $signupBegins,
                    'ends' => $signup->ends,
                ];
            }

            $person = [
                'id' => $signup->person_id,
                'callsign' => $signup->callsign,
            ];

            $parens = $parenthetical[$signup->position_id] ?? null;
            if ($parens) {
                $person['parenthetical'] = $parens;
            }

            if (!empty($signup->callsign_pronounce)) {
                $person['callsign_pronounce'] = $signup->callsign_pronounce;
            }

            $shifts[$signupBegins]['people'][] = $person;
        }

        ksort($shifts);

        $shifts = array_values($shifts);

        foreach ($shifts as &$s) {
            usort($s['people'], fn ($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
        }

        return $shifts;
    }

    /**
     * The pair of raw SELECT expressions that round begins/ends to the nearest hour
     * (add EPOCH_ROUND_MINUTES, then truncate to the hour) and alias them as the epoch columns.
     *
     * @return array<int, Expression> [begins_epoch expression, ends_epoch expression]
     */
    private static function epochSelects(): array
    {
        return [
            self::epochExpression('begins', 'begins_epoch'),
            self::epochExpression('ends', 'ends_epoch'),
        ];
    }

    /**
     * Build a single hourly-bucket epoch SELECT expression for the given source column.
     */
    private static function epochExpression(string $column, string $alias): Expression
    {
        $round = self::EPOCH_ROUND_MINUTES;

        return DB::raw("DATE_FORMAT(DATE_ADD($column, INTERVAL $round MINUTE),'%Y-%m-%d %H:00:00') as $alias");
    }

    /**
     * Apply the four-case interval-overlap predicate to a query builder.
     *
     * A row matches when its [begins, ends] interval overlaps the [$begins, $ends] window:
     * it spans the whole window, sits within it, ends within it, or begins within it.
     */
    private static function applyOverlapPredicate(QueryBuilder $where, Carbon $begins, Carbon $ends): void
    {
        // Shift spans the entire period
        $where->where(function (QueryBuilder $q) use ($begins, $ends) {
            $q->where('slot.begins', '<=', $begins);
            $q->where('slot.ends', '>=', $ends);
        })
            // Shift happens within the period
            ->orWhere(function (QueryBuilder $q) use ($begins, $ends) {
                $q->where('slot.begins', '>=', $begins);
                $q->where('slot.ends', '<=', $ends);
            })
            // Shift ends within the period
            ->orWhere(function (QueryBuilder $q) use ($begins, $ends) {
                $q->where('slot.ends', '>', $begins);
                $q->where('slot.ends', '<=', $ends);
            })
            // Shift begins within the period
            ->orWhere(function (QueryBuilder $q) use ($begins, $ends) {
                $q->where('slot.begins', '>=', $begins);
                $q->where('slot.begins', '<', $ends);
            });
    }

    /**
     * The PHP equivalent of {@see self::applyOverlapPredicate()}: does the row interval
     * [$rowBegins, $rowEnds] overlap the period [$periodBegins, $periodEnds]?
     *
     * NOTE: As in the original code, this compares the hour-rounded epoch timestamps,
     * whereas the SQL pre-fetch compares the raw begins/ends columns. That difference in
     * operands is preserved deliberately.
     */
    private static function epochOverlaps(int $rowBegins, int $rowEnds, int $periodBegins, int $periodEnds): bool
    {
        // Row spans the entire period
        return ($rowBegins <= $periodBegins && $rowEnds >= $periodEnds)
            // Row happens within the period
            || ($rowBegins >= $periodBegins && $rowEnds <= $periodEnds)
            // Row ends within the period
            || ($rowEnds > $periodBegins && $rowEnds <= $periodEnds)
            // Row begins within the period
            || ($rowBegins >= $periodBegins && $rowBegins < $periodEnds);
    }
}
